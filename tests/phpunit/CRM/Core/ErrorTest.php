<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Tests for linking to resource files
 * @group headless
 */
class CRM_Core_ErrorTest extends CiviUnitTestCase {

  public function setUp(): void {
    parent::setUp();
    $config = CRM_Core_Config::singleton();
    $this->oldConfigAndLogDir = $config->configAndLogDir;
    $config->configAndLogDir = $this->createTempDir('test-log-');
  }

  public function tearDown(): void {
    $config = CRM_Core_Config::singleton();
    $config->configAndLogDir = $this->oldConfigAndLogDir;
    parent::tearDown();
  }

  /**
   * Make sure that formatBacktrace() accepts values from debug_backtrace()
   */
  public function testFormatBacktrace_debug() {
    $bt = debug_backtrace();
    $msg = CRM_Core_Error::formatBacktrace($bt);
    $this->assertRegexp('/CRM_Core_ErrorTest->testFormatBacktrace_debug/', $msg);
  }

  /**
   * Make sure that formatBacktrace() accepts values from Exception::getTrace()
   */
  public function testFormatBacktrace_exception() {
    $e = new Exception('foo');
    $msg = CRM_Core_Error::formatBacktrace($e->getTrace());
    $this->assertRegexp('/CRM_Core_ErrorTest->testFormatBacktrace_exception/', $msg);
  }

  public function testExceptionLogging() {
    $e = new \Exception("the exception");
    Civi::log()->notice('There was an exception!', [
      'exception' => $e,
    ]);

    $e = new Error('the error');
    Civi::log()->notice('There was an error!', [
      'exception' => $e,
    ]);
  }

  /**
   * We have two coding conventions for writing to log. Make sure that they work together.
   *
   * This tests a theory about what caused CRM-10766.
   */
  public function testMixLog() {
    CRM_Core_Error::debug_log_message("static-1");
    $logger = CRM_Core_Error::createDebugLogger();
    CRM_Core_Error::debug_log_message("static-2");
    $logger->info('obj-1');
    CRM_Core_Error::debug_log_message("static-3");
    $logger->info('obj-2');
    CRM_Core_Error::debug_log_message("static-4");
    $logger2 = CRM_Core_Error::createDebugLogger();
    $logger2->info('obj-3');
    CRM_Core_Error::debug_log_message("static-5");
    $this->assertLogRegexp('/static-1.*static-2.*obj-1.*static-3.*obj-2.*static-4.*obj-3.*static-5/s');
  }

  /**
   * @param $pattern
   */
  public function assertLogRegexp($pattern) {
    $config = CRM_Core_Config::singleton();
    $logFiles = glob($config->configAndLogDir . '/CiviCRM*.log');
    $this->assertEquals(1, count($logFiles), 'Expect to find 1 file matching: ' . $config->configAndLogDir . '/CiviCRM*log*/');
    foreach ($logFiles as $logFile) {
      $this->assertRegexp($pattern, file_get_contents($logFile));
    }
  }

  /**
   * Check that a debugger is created and there is no error when passing in a prefix.
   *
   * Do some basic content checks.
   */
  public function testDebugLoggerFormat() {
    $log = CRM_Core_Error::createDebugLogger('my-test');
    $log->log('Mary had a little lamb');
    $log->log('Little lamb');
    $config = CRM_Core_Config::singleton();
    $fileContents = file_get_contents($log->_filename);
    $this->assertEquals($config->configAndLogDir . 'CiviCRM.' . CIVICRM_DOMAIN_ID . '_' . 'my-test.' . CRM_Core_Error::generateLogFileHash($config) . '.log', $log->_filename);
    // The 5 here is a bit arbitrary - on my local the date part is 15 chars (Mar 29 05:29:16) - but we are just checking that
    // there are chars for the date at the start.
    $this->assertTrue(strpos($fileContents, '[info] Mary had a little lamb') > 10);
    $this->assertStringContainsString('[info] Little lamb', $fileContents);
  }

}
