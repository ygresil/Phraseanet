<?php
/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2010 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @todo write tests
 *
 * @package     KonsoleKomander
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class module_console_systemUpgrade extends Command
{

  public function __construct($name = null)
  {
    parent::__construct($name);

    $this->setDescription('Upgrade Phraseanet to the lastest version');

    return $this;
  }

  public function execute(InputInterface $input, OutputInterface $output)
  {
    if(!setup::is_installed())
    {
      throw new RuntimeException('Phraseanet is not set up');
    }

    require_once dirname(__FILE__) . '/../../../../lib/bootstrap.php';

    $output->write('Phraseanet is going to be upgraded', true);
    $dialog = $this->getHelperSet()->get('dialog');

    do
    {
      $continue = mb_strtolower($dialog->ask($output, '<question>' . _('Continuer ?') . ' (Y/n)</question>', 'Y'));
    }
    while (!in_array($continue, array('y', 'n')));


    if ($continue == 'y')
    {
      try
      {
        $output->write('<info>Upgrading...</info>', true);
        $appbox = appbox::get_instance();
        $upgrader = new Setup_Upgrade($appbox);
        $advices = $appbox->forceUpgrade($upgrader);
      }
      catch (Exception $e)
      {
        $output->writeln(sprintf('<error>An error occured while upgrading : %s </error>', $e->getMessage()));
      }
    }
    else
    {
      $output->write('<info>Canceled</info>', true);
    }
    $output->write('Finished !', true);

    return;
  }

}