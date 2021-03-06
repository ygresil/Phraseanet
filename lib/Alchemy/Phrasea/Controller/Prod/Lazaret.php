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
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Lazaret controller collection
 *
 * Defines routes related to the lazaret (quarantine) functionality
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class Lazaret implements ControllerProviderInterface
{

    /**
     * Connect the ControllerCollection to the Silex Application
     *
     * @param  Application                 $app A silex application
     * @return \Silex\ControllerCollection
     */
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        /**
         * Lazaret Elements route
         *
         * name         : lazaret_elements
         *
         * description  : List all lazaret elements
         *
         * method       : GET
         *
         * parameters   : 'offset'      int (optional)  default 0   : List offset
         *                'limit'       int (optional)  default 10  : List limit
         *
         * return       : HTML Response
         */
        $controllers->get('/', $this->call('listElement'))
            ->bind('lazaret_elements');

        /**
         * Lazaret Element route
         *
         * name         : lazaret_element
         *
         * descritpion  : Get one lazaret element identified by {file_id} parameter
         *
         * method       : GET
         *
         * return       : JSON Response
         */
        $controllers->get('/{file_id}/', $this->call('getElement'))
            ->assert('file_id', '\d+')
            ->bind('lazaret_element');

        /**
         * Lazaret Force Add route
         *
         * name         : lazaret_force_add
         *
         * description  : Move a lazaret element identified by {file_id} parameter into phraseanet
         *
         * method       : POST
         *
         * parameters   : 'bas_id'            int     (mandatory) : The id of the destination collection
         *                'keep_attributes'   boolean (optional)  : Keep all attributes attached to the lazaret element
         *                'attributes'        array   (optional)  : Attributes id's to attach to the lazaret element
         *
         * return       : JSON Response
         */
        $controllers->post('/{file_id}/force-add/', $this->call('addElement'))
            ->assert('file_id', '\d+')
            ->bind('lazaret_force_add');

        /**
         * Lazaret Deny route
         *
         * name         : lazaret_deny_element
         *
         * description  : Remove a lazaret element identified by {file_id} parameter
         *
         * method       : POST
         *
         * return       : JSON Response
         */
        $controllers->post('/{file_id}/deny/', $this->call('denyElement'))
            ->assert('file_id', '\d+')
            ->bind('lazaret_deny_element');

        /**
         * Lazaret Accept Route
         *
         * name         : lazaret_accept
         *
         * description  : Substitute the phraseanet record identified by
         *                the post parameter 'record_id'by the lazaret element identified
         *                by {file_id} parameter
         *
         * method       : POST
         *
         * parameters   : 'record_id' int (mandatory) : The substitued record
         *
         * return       : JSON Response
         */
        $controllers->post('/{file_id}/accept/', $this->call('acceptElement'))
            ->assert('file_id', '\d+')
            ->bind('lazaret_accept');

        /**
         * Lazaret Thumbnail route
         *
         * name         : lazaret_thumbnail
         *
         * descritpion  : Get the thumbnail attached to the lazaret element
         *                identified by {file_id} parameter
         *
         * method       : GET
         *
         * return       : JSON Response
         */
        $controllers->get('/{file_id}/thumbnail/', $this->call('thumbnailElement'))
            ->assert('file_id', '\d+')
            ->bind('lazaret_thumbnail');

        return $controllers;
    }

    /**
     * List all elements in lazaret
     *
     * @param Application $app     A Silex application
     * @param Request     $request The current request
     *
     * @return Response
     */
    public function listElement(Application $app, Request $request)
    {
        $em = $app['phraseanet.core']->getEntityManager();
        $user = $app['phraseanet.core']->getAuthenticatedUser();
        /* @var $user \User_Adapter */
        $baseIds = array_keys($user->ACL()->get_granted_base(array('canaddrecord')));

        $lazaretFiles = null;

        if (count($baseIds) > 0) {
            $lazaretRepository = $em->getRepository('Entities\LazaretFile');

            $lazaretFiles = $lazaretRepository->findPerPage(
                $baseIds, $request->query->get('offset', 0), $request->query->get('limit', 10)
            );
        }

        $html = $app['twig']->render(
            'prod/upload/lazaret.html.twig', array('lazaretFiles' => $lazaretFiles)
        );

        return new Response($html);
    }

    /**
     * Get one lazaret Element
     *
     * @param Application $app     A Silex application
     * @param Request     $request The current request
     * @param int         $file_id A lazaret element id
     *
     * @return Response
     */
    public function getElement(Application $app, Request $request, $file_id)
    {
        $ret = array('success' => false, 'message' => '', 'result'  => array());

        $lazaretFile = $app['phraseanet.core']['EM']->find('Entities\LazaretFile', $file_id);

        /* @var $lazaretFile \Entities\LazaretFile */
        if (null === $lazaretFile) {
            $ret['message'] = _('File is not present in quarantine anymore, please refresh');

            return $app->json($ret);
        }

        $file = array(
            'filename' => $lazaretFile->getOriginalName(),
            'base_id'  => $lazaretFile->getBaseId(),
            'created'  => $lazaretFile->getCreated()->format(\DateTime::ATOM),
            'updated'  => $lazaretFile->getUpdated()->format(\DateTime::ATOM),
            'pathname' => $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getFilename(),
            'sha256'   => $lazaretFile->getSha256(),
            'uuid'     => $lazaretFile->getUuid(),
        );

        $ret['result'] = $file;
        $ret['success'] = true;

        return $app->json($ret);
    }

    /**
     * Add an element to phraseanet
     *
     * @param Application $app     A Silex application
     * @param Request     $request The current request
     * @param int         $file_id A lazaret element id
     *
     * @return Response
     */
    public function addElement(Application $app, Request $request, $file_id)
    {
        $ret = array('success' => false, 'message' => '', 'result'  => array());

        //Optional parameter
        $keepAttributes = ! ! $request->request->get('keep_attributes', false);
        $attributesToKeep = $request->request->get('attributes', array());

        //Mandatory parameter
        if (null === $baseId = $request->request->get('bas_id')) {
            $ret['message'] = _('You must give a destination collection');

            return $app->json($ret);
        }


        $lazaretFile = $app['phraseanet.core']['EM']->find('Entities\LazaretFile', $file_id);

        /* @var $lazaretFile \Entities\LazaretFile */
        if (null === $lazaretFile) {
            $ret['message'] = _('File is not present in quarantine anymore, please refresh');

            return $app->json($ret);
        }

        $lazaretFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getFilename();
        $lazaretThumbFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getThumbFilename();

        try {
            $borderFile = Border\File::buildFromPathfile(
                    $lazaretFileName, $lazaretFile->getCollection(), $lazaretFile->getOriginalName()
            );

            $record = null;
            /* @var $record \record_adapter */

            //Post record creation
            $callBack = function($element, $visa, $code) use (&$record) {
                    $record = $element;
                };

            //Force creation record
            $app['phraseanet.core']['border-manager']->process(
                $lazaretFile->getSession(), $borderFile, $callBack, Border\Manager::FORCE_RECORD
            );

            if ($keepAttributes) {
                //add attribute
                foreach ($lazaretFile->getAttributes() as $attr) {

                    //Check which ones to keep
                    if ( ! ! count($attributesToKeep)) {
                        if ( ! in_array($attr->getId(), $attributesToKeep)) {
                            continue;
                        }
                    }

                    try {
                        $attribute = Border\Attribute\Factory::getFileAttribute($attr->getName(), $attr->getValue());
                    } catch (\InvalidArgumentException $e) {
                        continue;
                    }

                    /* @var $attribute Border\Attribute\Attribute */

                    switch ($attribute->getName()) {
                        case Border\Attribute\Attribute::NAME_METADATA:
                            /**
                             * @todo romain neutron
                             */
                            break;
                        case Border\Attribute\Attribute::NAME_STORY:
                            $attribute->getValue()->appendChild($record);
                            break;
                        case Border\Attribute\Attribute::NAME_STATUS:
                            $record->set_binary_status($attribute->getValue());
                            break;
                        case Border\Attribute\Attribute::NAME_METAFIELD:
                            /**
                             * @todo romain neutron
                             */
                            break;
                    }
                }
            }

            //Delete lazaret file
            $app['phraseanet.core']['EM']->remove($lazaretFile);
            $app['phraseanet.core']['EM']->flush();

            $ret['success'] = true;
        } catch (\Exception $e) {
            $ret['message'] = _('An error occured');
        }

        try {
            $app['phraseanet.core']['file-system']->remove($lazaretFileName);
            $app['phraseanet.core']['file-system']->remove($lazaretThumbFileName);
        } catch (IOException $e) {

        }

        return $app->json($ret);
    }

    /**
     * Delete a lazaret element
     *
     * @param Application $app     A Silex application where the controller is mounted on
     * @param Request     $request The current request
     * @param int         $file_id A lazaret element id
     *
     * @return Response
     */
    public function denyElement(Application $app, Request $request, $file_id)
    {
        $ret = array('success' => false, 'message' => '', 'result'  => array());


        $lazaretFile = $app['phraseanet.core']['EM']->find('Entities\LazaretFile', $file_id);

        /* @var $lazaretFile \Entities\LazaretFile */
        if (null === $lazaretFile) {
            $ret['message'] = _('File is not present in quarantine anymore, please refresh');

            return $app->json($ret);
        }

        $lazaretFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getFilename();
        $lazaretThumbFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getThumbFilename();

        try {
            $app['phraseanet.core']['EM']->remove($lazaretFile);
            $app['phraseanet.core']['EM']->flush();

            $ret['success'] = true;
        } catch (\Exception $e) {
            $ret['message'] = _('An error occured');
        }

        try {
            $app['phraseanet.core']['file-system']->remove($lazaretFileName);
            $app['phraseanet.core']['file-system']->remove($lazaretThumbFileName);
        } catch (IOException $e) {

        }

        return $app->json($ret);
    }

    /**
     * Substitute a record element by a lazaret element
     *
     * @param Application $app     A Silex application where the controller is mounted on
     * @param Request     $request The current request
     * @param int         $file_id A lazaret element id
     *
     * @return Response
     */
    public function acceptElement(Application $app, Request $request, $file_id)
    {
        $ret = array('success' => false, 'message' => '', 'result'  => array());

        //Mandatory parameter
        if (null === $recordId = $request->request->get('record_id')) {
            $ret['message'] = _('You must give a destination record');

            return $app->json($ret);
        }

        $lazaretFile = $app['phraseanet.core']['EM']->find('Entities\LazaretFile', $file_id);

        /* @var $lazaretFile \Entities\LazaretFile */
        if (null === $lazaretFile) {
            $ret['message'] = _('File is not present in quarantine anymore, please refresh');

            return $app->json($ret);
        }

        $found = false;

        //Check if the choosen record is eligible to the substitution
        foreach ($lazaretFile->getRecordsToSubstitute() as $record) {
            if ($record->get_record_id() !== (int) $recordId) {
                continue;
            }

            $found = true;
            break;
        }

        if ( ! $found) {
            $ret['message'] = _('The destination record provided is not allowed');

            return $app->json($ret);
        }

        $lazaretFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getFilename();
        $lazaretThumbFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getThumbFilename();

        try {
            $media = $app['phraseanet.core']['mediavorus']->guess(new \SplFileInfo($lazaretFileName));

            $record = $lazaretFile->getCollection()->get_databox()->get_record($recordId);
            $record->substitute_subdef('document', $media);

            //Delete lazaret file
            $app['phraseanet.core']['EM']->remove($lazaretFile);
            $app['phraseanet.core']['EM']->flush();

            $ret['success'] = true;
        } catch (\Exception $e) {
            $ret['message'] = _('An error occured');
        }

        try {
            $app['phraseanet.core']['file-system']->remove($lazaretFileName);
            $app['phraseanet.core']['file-system']->remove($lazaretThumbFileName);
        } catch (IOException $e) {

        }

        return $app->json($ret);
    }

    /**
     * Get the associated lazaret element thumbnail
     *
     * @param Application $app     A Silex application where the controller is mounted on
     * @param Request     $request The current request
     * @param int         $file_id A lazaret element id
     *
     * @return Response
     */
    public function thumbnailElement(Application $app, Request $request, $file_id)
    {
        $lazaretFile = $app['phraseanet.core']['EM']->find('Entities\LazaretFile', $file_id);

        /* @var $lazaretFile \Entities\LazaretFile */
        if (null === $lazaretFile) {

            return new Response(null, 404);
        }

        $lazaretThumbFileName = $app['phraseanet.core']['Registry']->get('GV_RootPath') . 'tmp/lazaret/' . $lazaretFile->getThumbFilename();

        $response = \set_export::stream_file(
                $lazaretThumbFileName, $lazaretFile->getOriginalName(), 'image/jpeg', 'inline'
        );

        return $response;
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
