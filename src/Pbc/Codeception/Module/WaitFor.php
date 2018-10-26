<?php

Namespace Pbc\Codeception\Module;
use \Codeception\Module as CodeceptionModule;

/**
 * Class WaitFor
 * @package Pbc\Codeception\Module
 */
class WaitFor extends CodeceptionModule
{

    /**
     * Repeatedly check the page to see if text shows up in a specific time frame.
     * Occasionally $I->waitForTest will fail if the text is already on the page
     * prior to the function querying the page.
     *
     * @param $I
     * @param $text
     * @param int $wait
     */
    public function waitForTextToShowUp($I, $text, $wait=10, $selector=null)
    {
        $caught = false;
        for($i=0; $i < $wait; $i++) {
            try {
                $I->see($text, $selector);
                $caught = true;
                break;
            } catch (\Exception $ex) {
                // didn't see the text, still waiting
                $I->wait(1);
            }
        }
        if($caught === false) {
            $I->fail('Could not find the message "'. $text .'" on the page.');
        }
    }
    /**
     * Repeatedly check the page to see if element shows up in a specific time frame.
     * Occasionally $I->waitForElement will fail if the element is already on the page
     * prior to the function querying the page.
     *
     * @param $I
     * @param $text
     * @param int $wait
     */
    public function waitForElementToShowUp($I, $element, $wait=10)
    {
        $caught = false;
        for($i=0; $i < $wait; $i++) {
            try {
                $I->seeElement($element);
                $caught = true;
                break;
            } catch (\Exception $ex) {
                // didn't see the text, still waiting
                $I->wait(1);
            }
        }
        if($caught === false) {
            $I->fail('Could not find the element "'. json_encode($element) .'" on the page.');
        }
    }

    /**
     * Repeatedly check the page to see if element shows up in the DOM in a specific time frame.
     * Occasionally $I->waitForElementInDom will fail if the element is already on the page
     * prior to the function querying the page.
     *
     * @param $I
     * @param $text
     * @param int $wait
     */
    public function waitForElementToShowUpInDOM($I, $element, $wait=10)
    {
        $caught = false;
        for($i=0; $i < $wait; $i++) {
            try {
                $I->seeElementInDOM($element);
                $caught = true;
                break;
            } catch (\Exception $ex) {
                // didn't see the text, still waiting
                $I->wait(1);
            }
        }
        if($caught === false) {
            $I->fail('Could not find the element "'. json_encode($element) .'" in the DOM.');
        }
    }
}