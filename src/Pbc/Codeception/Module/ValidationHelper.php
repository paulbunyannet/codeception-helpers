<?php
namespace Pbc\Codeception\Module;
use Codeception\Module as CodeceptionModule;

/**
 * Class WaitFor
 *
 * @package Pbc\Codeception\Module
 */
class ValidationHelper extends CodeceptionModule
{

    /**
     * Validate page HTML
     *
     * @param \AcceptanceTester $I          Actor
     * @param string            $identifier Name of file use to check the html
     *
     * @see https://github.com/validator/validator/wiki/Service-%C2%BB-Input-%C2%BB-POST-body
     */
    public function validateHtml($I, $identifier)
    {
        $I->makeScreenshot($identifier);
        $source = $I->grabPageSource();
        $filePath = codecept_output_dir($identifier . '.html');
        $outputFilePath = $filePath . '.json';
        $handle = fopen($filePath, 'w');
        fwrite($handle, $source);
        fclose($handle);
        exec(
            'curl -H "Content-Type: text/html; charset=utf-8" --data-binary @' . $filePath . ' https://validator.w3.org/nu/?out=json > ' . $outputFilePath
        );
        $result = json_decode(file_get_contents($outputFilePath));
        codecept_debug($result);
        if (property_exists($result, 'messages') && count($result->messages) > 0) {
            $I->fail($I->grabFromCurrentUrl() . PHP_EOL . ucfirst($result->messages[0]->type) . ' on line ' . $filePath . PHP_EOL . (string)$result->messages[0]->lastLine . ': ' . $result->messages[0]->message . '(' . $result->messages[0]->extract . ')');
        }
    }
}
