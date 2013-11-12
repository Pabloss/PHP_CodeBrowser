<?php

use Symfony\Component\Console\Application as AbstractApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class CbCliApplication extends AbstractApplication
{

  /**
   * Public constructor
   */
  public function __construct()
  {
    parent::__construct('PHP_CodeBrowser', '1.0.4');
  }

  /**
   * Gets the name of the command based on input.
   *
   * @param InputInterface $input The input interface
   *
   * @return string The command name
   */
  protected function getCommandName(InputInterface $input)
  {
    return 'phpcb';
  }

  /**
   * Gets the default commands that should always be available.
   *
   * @return array An array of default Command instances
   */
  protected function getDefaultCommands()
  {
    $defaultCommands = parent::getDefaultCommands();

    $defaultCommands[] = new CbCliCommand;

    return $defaultCommands;
  }

  /**
   * Overridden so that the application doesn't expect the command
   * name to be the first argument.
   */
  public function getDefinition()
  {
    $inputDefinition = parent::getDefinition();
    $inputDefinition->setArguments();

    return $inputDefinition;
  }

  /**
   * Runs the current application.
   *
   * @param InputInterface  $input  An Input instance
   * @param OutputInterface $output An Output instance
   *
   * @return integer 0 if everything went fine, or an error code
   */
  public function doRun(InputInterface $input, OutputInterface $output)
  {
    if (!$input->getFirstArgument()) {
      $input = new ArrayInput(array('--help'));
    }

    parent::doRun($input, $output);
  }

}
