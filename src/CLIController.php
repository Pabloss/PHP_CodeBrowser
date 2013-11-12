<?php
/**
 * Cli controller
 *
 * PHP Version 5.3.2
 *
 * Copyright (c) 2007-2010, Mayflower GmbH
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Mayflower GmbH nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  PHP_CodeBrowser
 * @package   PHP_CodeBrowser
 * @author    Elger Thiele <elger.thiele@mayflower.de>
 * @author    Simon Kohlmeyer <simon.kohlmeyer@mayflower.de>
 * @copyright 2007-2010 Mayflower GmbH
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   SVN: $Id$
 * @link      http://www.phpunit.de/
 * @since     File available since  0.1.0
 */

if (strpos('@php_dir@', '@php_dir') === false) {
    if (!defined('PHPCB_ROOT_DIR')) {
        define('PHPCB_ROOT_DIR', '@php_dir@/PHP_CodeBrowser');
    }
    if (!defined('PHPCB_TEMPLATE_DIR')) {
        define('PHPCB_TEMPLATE_DIR', '@data_dir@/PHP_CodeBrowser/templates');
    }
} else {
    if (!defined('PHPCB_ROOT_DIR')) {
        define('PHPCB_ROOT_DIR', realpath(dirname(__FILE__) . '/../'));
    }
    if (!defined('PHPCB_TEMPLATE_DIR')) {
        define('PHPCB_TEMPLATE_DIR', realpath(dirname(__FILE__) . '/../templates'));
    }
}

require_once dirname(__FILE__) . '/Autoload.php';
require_once 'File/Iterator/Autoload.php';

if (stream_resolve_include_path('Symfony/Component/Console/autoloader.php')) {
    require_once 'Symfony/Component/Console/autoloader.php';
}

/**
 * CbCLIController
 *
 * @category  PHP_CodeBrowser
 * @package   PHP_CodeBrowser
 * @author    Elger Thiele <elger.thiele@mayflower.de>
 * @author    Michel Hartmann <michel.hartmann@mayflower.de>
 * @author    Simon Kohlmeyer <simon.kohlmeyer@mayflower.de>
 * @copyright 2007-2010 Mayflower GmbH
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   Release: @package_version@
 * @link      http://www.phpunit.de/
 * @since     Class available since  0.1.0
 */
class CbCLIController
{
    /**
     * Path to the Cruise Control input xml file
     *
     * @var string
     */
    private $_logDir;

    /**
     * Path to the code browser html output folder
     *
     * @var string
     */
    private $_htmlOutputDir;

    /**
     * Path to the project source code files
     *
     * @var string
     */
    private $_projectSource;

    /**
     * Array of PCREs. Matching files will not appear in the output.
     *
     * @var Array
     */
    private $_excludeExpressions;

    /**
     * Array of glob patterns. Matching files will not appear in the output.
     *
     * @var Array
     */
    private $_excludePatterns;

    /**
     * The error plugin classes
     *
     * @var array
     */
    private $_registeredPlugins;

    /**
     * The IOHelper used for filesystem interaction.
     *
     * @var CbIOHelper
     */
    private $_ioHelper;

    /**
     * Plugin-specific options. Formatted like
     *  array(
     *      'CbErrorCRAP' => array(
     *          'threshold' => 2
     *      )
     *  )
     *
     * @var array
     */
    private $_pluginOptions = array();

    /**
     * File extensions that we take as php files.
     *
     * @var array
     */
    private $_phpSuffixes;

    /**
     * We want to exclude files with no issues
     *
     * @var boolean
     */
    private $_excludeOK;

    /**
     * The constructor, standard setters are initialized
     *
     * @param string     $logPath            The (path-to) xml log files. Can be null.
     * @param array      $projectSource      The project sources. Can be null.
     * @param string     $htmlOutputDir      The html output dir, where new files will be created
     * @param array      $excludeExpressions A list of PCREs. Files matching will not appear in the output.
     * @param array      $excludePatterns    A list of glob patterns. Files matching
     * @param array      $pluginOptions      Array of Arrays with plugin-specific options will not appear in the output.
     * @param CbIOHelper $ioHelper           The CbIOHelper object to be used for filesystem interaction.
     * @param array      $phpSuffixes        PHP Suffixes
     * @param boolean    $excludeOK          Exclude OK ?
     */
    public function __construct($logPath, array $projectSource,
                                $htmlOutputDir, array $excludeExpressions,
                                array $excludePatterns, array $pluginOptions,
                                CbIOHelper $ioHelper,
                                array $phpSuffixes, $excludeOK = false)
    {
        $this->_logDir             = $logPath;
        $this->_projectSource      = $projectSource;
        $this->_htmlOutputDir      = $htmlOutputDir;
        $this->_excludeExpressions = $excludeExpressions;
        $this->_excludePatterns    = $excludePatterns;
        foreach ($pluginOptions as $plugin => $options) {
            $this->_pluginOptions["CbError$plugin"] = $options;
        }
        $this->_ioHelper           = $ioHelper;
        $this->_registeredPlugins  = array();
        $this->_phpSuffixes        = $phpSuffixes;
        $this->_excludeOK          = $excludeOK;
    }

    /**
     * Setter/adder method for the used plugin classes.
     * For each plugin to use, add it to this array
     *
     * @param mixed $classNames Definition of plugin classes
     *
     * @return void
     */
    public function addErrorPlugins($classNames)
    {
        foreach ((array) $classNames as $className) {
            $this->_registeredPlugins[] = $className;
        }
    }

    /**
     * Main execute function for PHP_CodeBrowser.
     *
     * Following steps are resolved:
     * 1. Clean-up output directory
     * 2. Merge xml log files
     * 3. Generate cbXML file via errorlist from plugins
     * 4. Save the cbErrorList as XML file
     * 5. Generate HTML output from cbXML
     * 6. Copy ressources (css, js, images) from template directory to output
     *
     * @return void
     */
    public function run()
    {
        // clear and create output directory
        if (is_dir($this->_htmlOutputDir)) {
            $this->_ioHelper->deleteDirectory($this->_htmlOutputDir);
        } else if (is_file($this->_htmlOutputDir)) {
            $this->_ioHelper->deleteFile($this->_htmlOutputDir);
        }
        $this->_ioHelper->createDirectory($this->_htmlOutputDir);

        // init needed classes
        $cbViewReview  = new CbViewReview(
            PHPCB_TEMPLATE_DIR,
            $this->_htmlOutputDir,
            $this->_ioHelper,
            $this->_phpSuffixes
        );

        $sourceHandler = new CbSourceHandler();

        if (isset($this->_logDir)) {
            $cbIssueXml    = new CbIssueXml();

            // merge xml files
            $cbIssueXml->addDirectory($this->_logDir);

            // conversion of XML file cc to cb format
            foreach ($this->_registeredPlugins as $className) {
                if (array_key_exists($className, $this->_pluginOptions)) {
                    $plugin = new $className(
                        $cbIssueXml,
                        $this->_pluginOptions[$className]
                    );
                } else {
                    $plugin = new $className($cbIssueXml);
                }
                $sourceHandler->addPlugin($plugin);
            }
        }

        if (isset($this->_projectSource)) {
            foreach ($this->_projectSource as $source) {
                if (is_dir($source)) {
                    $factory = new File_Iterator_Factory;

                    $suffixes = array_merge(
                        $this->_phpSuffixes,
                        array('php','js','css', 'html')
                    );

                    $sourceHandler->addSourceFiles(
                        $factory->getFileIterator(
                            $source,
                            $suffixes
                        )
                    );
                } else {
                    $sourceHandler->addSourceFile($source);
                }
            }
        }

        array_walk(
            $this->_excludeExpressions,
            array($sourceHandler, 'excludeMatchingPCRE')
        );
        array_walk(
            $this->_excludePatterns,
            array($sourceHandler, 'excludeMatchingPattern')
        );

        $files = $sourceHandler->getFiles();

        if (!$files) {
            $cbViewReview->copyNoErrorsIndex();
        } else {
            // Get the path prefix all files have in common
            $commonPathPrefix = $sourceHandler->getCommonPathPrefix();

            $error_reporting = ini_get('error_reporting');
            // Disable E_Strict, Text_Highlighter might throw up
            ini_set('error_reporting', $error_reporting & ~E_STRICT);
            foreach ($files as $file) {
                /* @var $file CbFile */
                $cbViewReview->generate(
                    $file->getIssues(),
                    $file->name(),
                    $commonPathPrefix,
                    $this->_excludeOK
                );
            }
            ini_set('error_reporting', $error_reporting);

            // Copy needed ressources (eg js libraries) to output directory
            $cbViewReview->copyRessourceFolders();
            $cbViewReview->generateIndex($files, $this->_excludeOK);
        }
    }
}
