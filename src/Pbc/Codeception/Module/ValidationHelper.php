<?php
namespace Pbc\Codeception\Module;
use Codeception\Module as CodeceptionModule;
use Pbc\Codeception\Helpers\Colors;

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

            for($i=0, $iCount=count($result->messages); $i < $iCount; $i++) {
                if ($result->messages[$i]->type === 'error') {
                    $this->renderResponse($I, $result->messages[$i], 'fail', 'red', $filePath);
                }
                $this->renderResponse($I, $result->messages[$i], 'comment', 'yellow', $filePath);
            }
        }
    }

    /**
     * @param \AcceptanceTester $I
     * @param object            $response The response array from W3C
     * @param string            $type     Type of output, `comment` or `fail`
     * @param string            $color    Type color
     * @param string            $filePath Path to the checked file
     */
    protected function renderResponse($I, $response, $type, $color, $filePath)
    {
        $I->{$type}(
            (new Colors())
                ->getColoredString(
                    'On URL: ' . $I->grabFromCurrentUrl() . PHP_EOL .
                    ucfirst($response->type) .
                    (property_exists($response,'subType') ? ' ('.  ucfirst($response->subType) .')' : '') .
                    ' on line ' . $filePath . ':' . (string)$response->lastLine . PHP_EOL .
                    (string)$response->lastLine . ': ' . $response->message . '(' . $response->extract . ')',
                    $color
                )
        );
    }
}
