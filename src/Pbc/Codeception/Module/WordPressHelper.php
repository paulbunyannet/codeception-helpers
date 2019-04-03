<?php
Namespace Pbc\Codeception\Module;

use Codeception\Module as CodeceptionModule;
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
    public function createAPost($I, $title = null, $content = null, $adminPath='/wp-admin')
    {
        $faker = \Faker\Factory::create();
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

        $wpPath = getcwd() . "/public_html/wp";
        $slug = \Cocur\Slugify\Slugify::create()->slugify($title);
        $wpCommand = 'bin/wp --allow-root --skip-packages --skip-plugins --skip-themes --path='. $wpPath .' post create --porcelain --post_status=publish --post_name='. $slug . ' --post_title="' . $title . '" --post_content=\'' . str_replace("'", "\'", $content) . '\'';
        if (isset($customFields) && count($customFields) > 0) {
            $meta = [];
            for ($i=0, $iCount=count($customFields); $i < $iCount; $i++) {
                    $meta[$customFields[$i][0]] = $customFields[$i][1];
            }
            $wpCommand .= " --meta_input='" . json_encode($meta) . "'";
        }
        $I->runShellCommand($wpCommand);
        // Save ID for featured image purposes
        // TODO: Make featured images work
        $postID = trim($this->getModule('Cli')->output);
        $I->amOnPage("/$slug/");
    }
}
