<?php

use Symfony\Component\Console\Command\Command as AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CbCliCommand extends AbstractCommand
{

  /**
   * Executes the current command.
   *
   * @param InputInterface  $input  An InputInterface instance
   * @param OutputInterface $output An OutputInterface instance
   *
   * @return null|integer null or 0 if everything went fine, or an error code
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $opts = $input->getOptions();

    // Convert the --ignore arguments to patterns
    if ($opts['ignore']) {
      $dirSep = preg_quote(DIRECTORY_SEPARATOR, '/');
      foreach (explode(',', $opts['ignore']) as $ignore) {
        $ig = realpath($ignore);
        if (!$ig) {
          error_log("[Warning] $ignore does not exists");
        } else {
          $ig = preg_quote($ig, '/');
          $opts['excludePCRE'][] = "/^$ig($dirSep|$)/";
        }
      }
    }

    // init new CLIController
    $controller = new CbCLIController(
      $opts['log'],
      $opts['source'],
      $opts['output'],
      $opts['excludePCRE'],
      $opts['exclude'],
      array('CRAP' => array(
        'threshold' => $opts['crapThreshold'])
      ),
      new CbIOHelper(),
      explode(',', $opts['extensions']),
      $opts['excludeOK']
    );

    $plugins = $this->_getAvailablePlugins();
    foreach ($opts['disablePlugin'] as $idx => $val) {
      $opts['disablePlugin'][$idx] = strtolower($val);
    }
    foreach ($plugins as $pluginKey => $plugin) {
      $name = substr($plugin, strlen('CbError'));
      if (in_array(strtolower($name), $opts['disablePlugin'])) {
        // Remove it from the plugins list
        unset($plugins[$pluginKey]);
      }
    }
    $controller->addErrorPlugins($plugins);

    try {
      $controller->run();
      $result = 0;
    } catch (Exception $e) {
      $result = $e->getCode() ?: 1;
    }
    return $result;
  }

  /**
   * Configures the current command.
   */
  protected function configure()
  {
    $this->setName("phpcb");
    $this->setHelp(
      "A Code browser for PHP files with syntax highlighting and colored error-sections found by quality assurance tools like PHPUnit or PHP_CodeSniffer"
    );

    $this->addOption(
      "log", "l", InputOption::VALUE_OPTIONAL,
      "The path to the xml log files, e.g. generated from PHPUnit.\nEither this or --source must be given"
    );
    $this->addOption(
      "output", "o", InputOption::VALUE_OPTIONAL,
      "Path to the output folder where generated files should be stored"
    );
    $this->addOption(
      "source", "s", InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
      "Path to the project source code. Can either be a directory or\na single file. Parse complete source directory if set,\nelse only files found in logs.\nEither this or --log must be given."
    );
    $this->addOption(
      "extensions", "S", InputOption::VALUE_OPTIONAL,
      "A comma separated list of php file extensions to include.",
      "php"
    );
    $this->addOption(
      "ignore", "i", InputOption::VALUE_OPTIONAL,
      "Comma separated string of files or directories that\nwill be ignored during the parsing process."
    );
    $this->addOption(
      "exclude", "e", InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
      "Excludes all files matching the given glob pattern.\nThis is done after pulling the files in the source dir\nin if one is given. Can be given multiple times. Note\nthat the match is run against absolute file names."
    );
    $this->addOption(
      "excludePCRE", "E", InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
      "Works like -e but takes PCRE instead of glob patterns."
    );
    $this->addOption(
      "debugExcludes", null, InputOption::VALUE_NONE,
      "Print which files are excluded by which expressions and patterns."
    );
    $this->addOption(
      "excludeOK", null, InputOption::VALUE_NONE,
      "Exclude files with no issues from the report."
    );
    $this->addOption(
      "disablePlugin", null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
      "Disable single Plugins. Can be one of\n" . implode(", ", $this->_getAvailablePlugins())
    );
    $this->addOption(
      "crapThreshold", null, InputOption::VALUE_OPTIONAL,
      "The minimum value for CRAP errors to be recognized.\nRegardless of this setting, values below 30 will be\nconsidered notices, those above warnings.",
      0
    );
  }

  /**
   * Returns a list of available plugins.
   *
   * Currently hard-coded.
   *
   * @return array of string Classnames of error plugins
   */
  private function _getAvailablePlugins()
  {
    return array(
      'CbErrorCheckstyle',
      'CbErrorPMD',
      'CbErrorCPD',
      'CbErrorPadawan',
      'CbErrorCoverage',
      'CbErrorCRAP'
    );
  }

}
