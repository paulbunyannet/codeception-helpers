<?php
namespace Pbc\Codeception\Module;

use Cocur\Slugify\Slugify;
use Codeception\Module as CodeceptionModule;
use Faker\Factory;
use Pbc\Bandolier\Type\Arrays as BandolierArrays;


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
        $I->waitForText('You are now logged out.', self::TEXT_WAIT_TIMEOUT);
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string                                $user   Username to login with
     * @param string                                $pass   Password to login with
     * @throws \Exception
     */
    private function fillLoginAndWaitForDashboard($I, $user = "admin", $pass = "password")
    {
        $I->waitForJs('return document.readyState == "complete"', self::TEXT_WAIT_TIMEOUT);
        $I->fillField(['id' => 'user_login'], $user);
        $I->fillField(['id' => 'user_pass'], $pass);
        $I->checkOption("#rememberme");
        $I->click(['id' => 'wp-submit']);
        $I->waitForElementNotVisible("#rememberme", self::TEXT_WAIT_TIMEOUT * 2);
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     * @param string                                $filePath   Path to an image file in /tests/Support/Data folder
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
            $wpCommand = 'bin/wp --allow-root --skip-packages --skip-plugins --skip-themes --path='. $wpPath .' media import tests/Support/Data/'. $featured_image . ' --porcelain --featured_image --post_id=' . $postID;
            $I->runShellCommand($wpCommand);
            $attachmentId = trim($this->getModule('Cli')->output);
        }
        $I->runShellCommand("bin/wp --allow-root --skip-packages --skip-plugins --skip-themes --path=". $wpPath ." post get ". $postID ." --field=url");
        $url = trim($this->getModule('Cli')->output);
        $I->amOnPage($url);
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

        // Get rid of Welcome Modal
        $I->click('//*[@class="components-modal__content"]/div/button');

        // Enable code editor
        $I->waitForElementClickable(['class' => 'interface-more-menu-dropdown'], self::TEXT_WAIT_TIMEOUT);
        $I->click(['class' => 'interface-more-menu-dropdown']);
        $I->waitForElementVisible(['id' => 'components-menu-group-label-1'], self::TEXT_WAIT_TIMEOUT);
        $I->click('//*[@id="editor"]/div/div[2]/div/div/div/div[2]/div[2]/button[2]/span[1]');

        // Fill Title
        $I->scrollTo(['class' => 'wp-block-post-title']);
        $I->fillField(['class' => 'wp-block-post-title'], $title);

        // Fill Content
        $I->click(['class' => 'editor-post-text-editor']);
        $I->fillField(['class' => 'editor-post-text-editor'], $content);

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
                    $I->waitForElementClickable(['id' => 'enternew'], self::TEXT_WAIT_TIMEOUT);
                    $I->click(['id' => 'enternew']);
                    $I->fillField(['id' =>'metakeyinput'], $customFields[$i][0]);
                    $I->fillField(['id' =>'metavalue'], $customFields[$i][1]);
                    $I->click(['id' => 'newmeta-submit']);
                }
            }
        }

        // add featured image if it's set
        if (isset($featured_image) && is_string($featured_image)) {
            $I->click('//*[@id="editor"]/div/div[1]/div[1]/div[2]/div[3]/div/div[3]/div[4]/h2/button');
            $I->click('Set featured image');

            $I->click('//*[@id="__attachments-view-77"]/li[@aria-label="'.$featured_image.'"]');
            $I->click('#__wp-uploader-id-2 .media-button');
            $I->waitForElementVisible(['class' => 'editor-post-featured-image__preview'], self::TEXT_WAIT_TIMEOUT);
        }

        $I->scrollTo(['class' => 'editor-post-publish-panel__toggle']);

        $I->click(['class' => 'editor-post-publish-panel__toggle']);

        $I->waitForElementVisible(['class' => 'editor-post-publish-button'], self::TEXT_WAIT_TIMEOUT);
        $I->click(['class' => 'editor-post-publish-button']);


        $I->waitForText('Post published', self::TEXT_WAIT_TIMEOUT);
        $I->see('Post published');

        $I->click('//*[@id="editor"]/div/div[1]/div[1]/div[2]/div[4]/div[2]/div/div/div[2]/div/div[2]/div[2]/a[1]');
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     */
    public function enterTag($I, string $tag)
    {
        $I->click('//*[@id="editor"]/div/div[1]/div/div[2]/div[3]/div/div[3]/div[3]/h2/button');
        $I->fillField(['id' => 'components-form-token-input-0'], $tag);
        $I->waitForElementVisible(['id' => 'components-form-token-suggestions-0-0'], self::TEXT_WAIT_TIMEOUT);
        $I->click(['id' => 'components-form-token-suggestions-0-0']);
    }

    /**
     * @param \AcceptanceTester|\FunctionalTester   $I
     */
    public function updatePost($I)
    {
        $I->scrollTo(['class' => 'editor-post-publish-button']);
        $I->click(['class' => 'editor-post-publish-button']);
        $I->waitForText('Post updated', self::TEXT_WAIT_TIMEOUT);
    }
}
