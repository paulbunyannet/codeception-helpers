<?php
namespace Pbc\Codeception\Module;
use \Codeception\Module as CodeceptionModule;

/**
 * Class WaitFor
 * @package Pbc\Codeception\Module
 */
class ValidationHelper extends CodeceptionModule
{

  /**
   * Validate page HTML
   * @see https://github.com/validator/validator/wiki/Service-%C2%BB-Input-%C2%BB-POST-body
   * @param AcceptanceTester $I
   * @param string           $from String used for naming screenshots and source files
   */
  public function validateHtml(AcceptanceTester $I, $from)
  {
      $I->makeScreenshot($from);
      $source = $I->grabPageSource();
      $filePath = codecept_output_dir($from . '.html');
      $outputFilePath = $filePath . '.json';
      $handle = fopen($filePath, 'w');
      fwrite($handle, $source);
      fclose($handle);
      exec('curl -H "Content-Type: text/html; charset=utf-8" --data-binary @'. $filePath .' https://validator.w3.org/nu/?out=json > '. $outputFilePath);
      $result = json_decode(file_get_contents($outputFilePath));
      codecept_debug($result);
      if (property_exists($result, 'messages') && count($result->messages) > 0) {
          $I->fail(ucfirst($result->messages[0]->type).' on line '. $filePath.':'.(string)$result->messages[0]->lastLine.': '. $result->messages[0]->message . '('. $result->messages[0]->extract .')');
      }
  }
}
