<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2012 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Controller\Prod;

use Alchemy\Phrasea\Border;
use MediaVorus\MediaVorus;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

/**
 * Upload controller collection
 *
 * Defines routes related to the Upload process in phraseanet
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class Upload implements ControllerProviderInterface
{

    /**
     * Connect the ControllerCollection to the Silex Application
     *
     * @param  Application                 $app A silex application
     * @return \Silex\ControllerCollection
     */
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * Upload form route
         *
         * name         : upload_form
         *
         * description  : Render the html upload form
         *
         * method       : GET
         *
         * return       : HTML Response
         */
        $app->get('/upload/', $this->call('getUploadForm'))
            ->bind('upload_form');

        /**
         * UPLOAD route
         *
         * name         : upload
         *
         * description  : Initiate the upload process
         *
         * method       : POST
         *
         * parameters   : 'bas_id'        int     (mandatory) :   The id of the destination collection
         *                'status'        array   (optional)  :   The status to set to new uploaded files
         *                'attributes'    array   (optional)  :   Attributes id's to attach to the uploaded files
         *                'forceBehavior' int     (optional)  :   Force upload behavior
         *                      - 0 Force record
         *                      - 1 Force lazaret
         *
         * return       : JSON Response
         */
        $app->post('/upload/', $this->call('upload'))
            ->bind('upload');

        return $controllers;
    }

    /**
     * Render the html upload form
     *
     * @param Application $app     A Silex application
     * @param Request     $request The current request
     *
     * @return Response
     */
    public function getUploadForm(Application $app, Request $request)
    {
        $collections = array();
        $rights = array('canaddrecord');

        foreach ($app['Core']->getAuthenticatedUser()->ACL()->get_granted_base($rights) as $collection) {
            $databox = $collection->get_databox();
            if ( ! isset($collections[$databox->get_sbas_id()])) {
                $collections[$databox->get_sbas_id()] = array(
                    'databox'             => $databox,
                    'databox_collections' => array()
                );
            }

            $collections[$databox->get_sbas_id()]['databox_collections'][] = $collection;
        }

        $html = $app['Core']['Twig']->render(
            'prod/upload/upload.html.twig', array('collections' => $collections)
        );

        return new Response($html);
    }

    /**
     * Upload processus
     *
     * @param Application $app     The Silex application
     * @param Request     $request The current request
     *
     * @return Response
     */
    public function upload(Application $app, Request $request)
    {
        $datas = array(
            'success' => false,
            'code'    => null,
            'message' => '',
            'element' => '',
            'reasons' => array(),
            'id' => '',
        );

        if ( ! $request->files->get('files')) {
            throw new \Exception_BadRequest('Missing file parameter');
        }

        if (count($request->files->get('files')) > 1) {
            throw new \Exception_BadRequest('Upload is limited to 1 file per request');
        }

        $base_id = $request->get('base_id');

        if ( ! $base_id) {
            throw new \Exception_BadRequest('Missing base_id parameter');
        }

        if ( ! $app['Core']->getAuthenticatedUser()->ACL()->has_right_on_base($base_id, 'canaddrecord')) {
            throw new \Exception_Forbidden('User is not allowed to add record on this collection');
        }

        $file = current($request->files->get('files'));

        if ( ! $file->isValid()) {
            throw new \Exception_BadRequest('Uploaded file is invalid');
        }

        try {
            $media = MediaVorus::guess($file);
            $collection = \collection::get_from_base_id($base_id);

            $lazaretSession = new \Entities\LazaretSession();
            $lazaretSession->setUsrId($app['Core']->getAuthenticatedUser()->get_id());

            $app['Core']['EM']->persist($lazaretSession);

            $packageFile = new Border\File($media, $collection, $file->getClientOriginalName());

            $postStatus = $request->get('status');

            if (is_array($postStatus)) {

                $status = '';
                foreach (range(0, 64) as $i) {
                    $status .= isset($postStatus[$i]) ? ($postStatus[$i] ? '1' : '0') : '0';
                }
                $packageFile->addAttribute(new Border\Attribute\Status(strrev($status)));
            }

            $forceBehavior = $request->get('forceAction');

            $reasons = $elementCreated = null;

            $callback = function($element, $visa, $code) use (&$reasons, &$elementCreated) {
                    foreach ($visa->getResponses() as $response) {
                        if ( ! $response->isOk()) {
                            $reasons[] = $response->getMessage();
                        }
                    }
                    $elementCreated = $element;
                };

            $code = $app['Core']['border-manager']->process(
                $lazaretSession, $packageFile, $callback, $forceBehavior
            );


            if ($elementCreated instanceof \record_adapter) {
                $id = $elementCreated->get_serialize_key();
                $element = 'record';
                $reasons = array();
            } else {
                $id = $elementCreated->getId();
                $element = 'lazaret';
            }

            $datas = array(
                'success' => true,
                'code'    => $code,
                'message' => '',
                'element' => $element,
                'reasons' => $reasons,
                'id'      => $id,
            );
        } catch (\Exception $e) {

            $datas['message'] = _('Unable to add file to Phraseanet') . $e->getFile() . ':' . $e->getLine() . $e->getMessage();
        }

        return self::getJsonResponse($app['Core']['Serializer'], $datas);
    }

    private static function getJsonResponse(Serializer $serializer, Array $datas)
    {
        return new Response(
                $serializer->serialize($datas, 'json'),
                200,
                array('Content-type' => 'application/json')
        );
    }

    /**
     * Prefix the method to call with the controller class name
     *
     * @param  string $method The method to call
     * @return string
     */
    private function call($method)
    {
        return sprintf('%s::%s', __CLASS__, $method);
    }
}