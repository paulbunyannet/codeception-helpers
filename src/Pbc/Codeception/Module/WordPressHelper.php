<?php
namespace Pbc\Codeception\Module;

use Cocur\Slugify\Slugify;
use Codeception\Module as CodeceptionModule;
use Faker\Factory;
use Pbc\Bandolier\Type\Arrays as BandolierArrays;
use utilphp\util as Utilities;


/**
 * Class WpHelper
 * @package Codeception\Module
 * @backupGlobals disabled
 */
class WordPressHelper extends CodeceptionModule
{

    const TEXT_WAIT_TIMEOUT = 30;

    /**
     * Log a user into the Wordpress backend
     *
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string                                $loginPath      The login path (usually /wp-login.php)
     * @param string                                $user           Username to login with
     * @param string                                $pass           Password to login with
     * @param int                                   $maxAttempts    The maximum amount of times to attempt to login before failing
     */
    public function logIntoWpAdmin(
        $I,
        $loginPath = "/wp-login.php",
        $user = "admin",
        $pass = "password",
        $maxAttempts = 3
    ) {
        if (defined('WP_LOGIN_PATH')) {
            $loginPath = WP_LOGIN_PATH;
        }

        if (defined('WP_LOGIN_USER')) {
            $user = WP_LOGIN_USER;
        }

        if (defined('WP_LOGIN_PASSWORD')) {
            $pass = WP_LOGIN_PASSWORD;
        }

        if (defined('WP_LOGIN_MAX_ATTEMPTS')) {
            $maxAttempts = intval(WP_LOGIN_MAX_ATTEMPTS);
        }

        for ($i = 0; $i <= $maxAttempts; $i++) {
            try {
                $I->amOnPage($loginPath);
                $this->fillLoginAndWaitForDashboard($I, $user, $pass);
                if(((int) $I->executeJS("var element = document.getElementById('correct-admin-email'); if(typeof(element) != 'undefined' && element != null){ return 1; } return 0;")) === 1){
                    $I->click("#correct-admin-email");
                }
                $I->waitForText('Dashboard', self::TEXT_WAIT_TIMEOUT);
                return;
            } catch (\Exception $e) {
                if ($i === $maxAttempts) {
                    $I->fail("{$i} login attempts were made.");
                }
                continue;
            }
        }
    }

    /**
     * Logout of WordPress backend
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string                                $loginPath      The login path (usually /wp-login.php)
     * @throws \Exception
     */
    public function logOutOfWpAdmin($I, $loginPath = "/wp-login.php")
    {
        if (defined('WP_LOGIN_PATH')) {
            $loginPath = WP_LOGIN_PATH;
        }

        $I->amOnPage($loginPath . '?loggedout=true');
        $I->waitForText('You are now logged out.');
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string                                $user   Username to login with
     * @param string                                $pass   Password to login with
     * @throws \Exception
     */
    private function fillLoginAndWaitForDashboard($I, $user = "admin", $pass = "password")
    {
        $I->waitForJs('return document.readyState == "complete"', $this::TEXT_WAIT_TIMEOUT);
        $I->fillField(['id' => 'user_login'], $user);
        $I->fillField(['id' => 'user_pass'], $pass);
        $I->checkOption("#rememberme");
        $I->click(['id' => 'wp-submit']);
        $I->waitForElementNotVisible("#rememberme", $this::TEXT_WAIT_TIMEOUT * 2);
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string                                $filePath   Path to an image file in /tests/_data folder
     * @param string                                $adminPath  Path to the admin backend (usually /wp-admin)
     * @return array
     */
    public function createAnAttachment($I, $filePath, $adminPath='/wp-admin') {

        if (defined('WP_ADMIN_PATH')) {
            $adminPath = WP_ADMIN_PATH;
        }

        // Get the file path, the file should be in the test/_data directory
        $parts = explode(DIRECTORY_SEPARATOR, $filePath);
        $file = array_pop($parts);
        $path = implode(DIRECTORY_SEPARATOR, $parts);

        // Make a new file name for the file that we'll work with
        $fileNameParts = explode('.', $file);
        $newFileName = substr(md5(time()), 0, 10) . '.' . end($fileNameParts);
        $newFileNameParts = explode('.', $newFileName);

        // Create a copy of the file with a unique file name
        copy($path . DIRECTORY_SEPARATOR . $file, $path . DIRECTORY_SEPARATOR . $newFileName);

        $I->amOnPage($adminPath . '/media-new.php?browser-uploader');
        $I->attachFile(['id' => 'async-upload'], $newFileName);
        $I->click(['id' => 'html-upload']);
        $I->see('Media Library');
        $I->click($newFileNameParts[0]);
        return [
            'attachment_url' => $I->grabValueFrom(['id' => 'attachment_url'])
        ];
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string|array|null                     $title      Either the post title or an array of the attributes to use on the post
     * @param string|null                           $content    Body content of the page (if not in the $title variable)
     * @param string                                $adminPath  Path to the admin backend (usually /wp-admin)
     * @throws \Exception
     */
    public function createAPostCLI($I, $title = null, $content = null, $adminPath='/wp-admin')
    {
        $faker = Factory::create();
        if (is_array($title)) {
            extract(BandolierArrays::defaultAttributes([
                    'title' => $faker->sentence(),
                    'content' => $faker->paragraph(),
                    'options' => [],
                    'featured_image' => null,
                    'meta' => []
                ]
                , $title)
            );
        }

        $wpPath = getcwd() . "/public_html/wp";
        $slug = Slugify::create()->slugify($title);
        $wpCommand = 'bin/wp --allow-root --skip-packages --skip-plugins --skip-themes --path='. $wpPath .' post create --porcelain --post_status=publish --post_name='. $slug . ' --post_title="' . $title . '" --post_content=\'' . str_replace("'", "\'", $content) . '\'';
        if (isset($meta) && count($meta) > 0) {
            $meta_data = [];
            for ($i=0, $iCount=count($meta); $i < $iCount; $i++) {
                $meta_data[$meta[$i][0]] = $meta[$i][1];
            }
            $wpCommand .= " --meta_input='" . json_encode($meta_data) . "'";
        }
        if (isset($options) && count($options) > 0) {
            for ($i=0;$i < count($options); $i++) {
                $wpCommand .= " ".$options[$i][0]."=".$options[$i][1];
            }
        }
        $I->runShellCommand($wpCommand);
        $postID = trim($this->getModule('Cli')->output);
        if (isset($featured_image) && is_string($featured_image) && $postID) {
            $wpCommand = 'bin/wp --allow-root --skip-packages --skip-plugins --skip-themes --path='. $wpPath .' media import tests/_data/'. $featured_image . ' --porcelain --featured_image --post_id=' . $postID;
            $I->runShellCommand($wpCommand);
            $attachmentId = trim($this->getModule('Cli')->output);
        }
        $I->amOnPage("/$slug/");
        return $postID;
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string|array|null                     $title      Either the post title or an array of the attributes to use on the post
     * @param string|null                           $content    Body content of the page (if not in the $title variable)
     * @param string                                $adminPath  Path to the admin backend (usually /wp-admin)
     * @throws \Exception
     */
    public function createAPost($I, $title = null, $content = null, $adminPath='/wp-admin')
    {

        if (defined('WP_ADMIN_PATH')) {
            $adminPath = WP_ADMIN_PATH;
        }

        $faker = Factory::create();
        if (is_array($title)) {
            extract(BandolierArrays::defaultAttributes([
                    'title' => $faker->sentence(),
                    'content' => $faker->paragraph(),
                    'meta' => [],
                    'featured_image' => null,
                    'customFields' => []
                ]
                , $title)
            );
        }

        $I->amOnPage($adminPath . '/post-new.php');
        // show the settings dialog link
        $I->waitForElementVisible(['id' => 'show-settings-link']);
        $I->click(['id' => 'show-settings-link']);

        $I->scrollTo(['id' => 'title']);
        $I->fillField(['id' => 'title'], $title);
        $exist = Utilities::str_to_bool($I->executeJS("return !!document.getElementById('content-html')"));
        if ($exist) {
            $I->click(['id' => 'content-html']);
        }
        $I->click(['id' => 'content']);
        $I->fillField(['id' => 'content'], $content);

        // run though the meta field and set any extra fields that is contains
        if (isset($meta) && count($meta) > 0) {
            $I->scrollTo(['id' => 'screen-options-wrap']);
            for($i=0, $iCount=count($meta); $i < $iCount; $i++) {
                $I->{$meta[$i][0]}($meta[$i][1],$meta[$i][2]);
            }
        }
        // run though the custom fields. since there's no good way to know what
        // the name/id of the input is they will be looked up via the value
        if (isset($customFields) && count($customFields) > 0) {
            $I->scrollTo('#postcustom');
            for($i=0, $iCount=count($customFields); $i < $iCount; $i++) {
                try {
                    // try and fill an existing custom field
                    $I->fillField('#' . str_replace('key', 'value',
                            $I->executeJS('return document.querySelectorAll(\'input[value="' . $customFields[$i][0] . '"]\')[0].id;')),
                        $customFields[$i][1]);
                } catch (\Exception $ex) {
                    // make a new one if the above threw an exception
                    $I->click(['id' => 'enternew']);
                    $I->fillField(['id' =>'metakeyinput'], $customFields[$i][0]);
                    $I->fillField(['id' =>'metavalue'], $customFields[$i][1]);
                    $I->click(['id' => 'newmeta-submit']);
                }
            }
        }

        // add featured image if it's set
        if (isset($featured_image) && is_string($featured_image)) {
            $I->click('Set featured image');

            $I->click('//*[@id="__attachments-view-45"]/li[@aria-label="'.$featured_image.'"]');
            $I->click('#__wp-uploader-id-2 .media-button');
            $I->waitForElementVisible(['id' => 'remove-post-thumbnail'], self::TEXT_WAIT_TIMEOUT);
        }

        $I->executeJS('window.scrollTo(0,0);');
        $I->scrollTo(['id' => 'submitpost']);

        $I->click(['id' => 'publish']);
        $I->waitForText('Post published', self::TEXT_WAIT_TIMEOUT);
        $I->see('Post published');
        $path = $I->executeJS('return document.querySelector("#sample-permalink > a").getAttribute("href")');
        $I->amOnPage(parse_url($path, PHP_URL_PATH));
    }
}
