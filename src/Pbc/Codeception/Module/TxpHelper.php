<?php
/**
 * Class TextpatternHelper
 *
 * PHP Version >= 5.6
 *
 * @category Test
 * @package  Codeception\Module
 * @author   Nate Nolting <naten@paulbunyan.net>
 * @license  http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link     https://github.com/paulbunyannet/codeception-helpers/blob/master/src/Pbc/Codeception/Module/TxpHelper.php
 */

namespace Pbc\Codeception\Module;

use Codeception\Module;
use Codeception\Actor;
use Faker\Factory;
use Pbc\Bandolier\Type\Arrays;

/**
 * Class TextpatternHelper
 *
 * @category Test
 * @package  Codeception\Module
 * @author   Nate Nolting <naten@paulbunyan.net>
 * @license  http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link     https://github.com/paulbunyannet/codeception-helpers/blob/master/src/Pbc/Codeception/Module/TxpHelper.php
 *
 * @backupGlobals disabled
 */
class TxpHelper extends Module
{
    const KEY_LINKNAME = 'linkname';
    const KEY_LINKSORT = 'linksort';
    const KEY_URL = 'url';
    const KEY_CATEGORY = 'category';
    const KEY_DESCRIPTION = 'description';
    const KEY_THEFILE = 'thefile';
    const KEY_TITLE = 'title';
    const KEY_BODY = 'body';
    const KEY_URL_TITLE = 'url_title';
    const KEY_STATUS = 'status';
    const KEY_SECTION = 'section';
    const KEY_EXTRA = 'extra';

    /**
     * Upload an image in TXP
     *
     * Usage:
     *  // The second parameter should be an
     *  // array with overrides for
     *  // default keys if necessary
     *  // * thefile: The name of the file to upload. This file
     *                should be a file in the `tests/_data` folder.
     *  // See https://docs.textpattern.com/administration/images-panel for more info
     *  $I->uploadAnImageInTxp($I, ['thefile' => 'custom-upload-file.jpeg']);
     *
     * @param Actor $I    Text actor
     * @param array $data Data to pass to assertions
     *
     * @return void
     */
    public function uploadAnImageInTxp(Actor $I, $data = [])
    {
        $params = Arrays::defaultAttributes(
            [
                // the file name in tests/_data
                self::KEY_THEFILE => "one.png"
            ],
            $data
        );

        $I->amOnPage('/textpattern/index.php?event=image');
        $I->attachFile(self::KEY_THEFILE, $params[self::KEY_THEFILE]);
        $I->click('Upload');
        $I->waitForText('Image(s) '.$params[self::KEY_THEFILE].' uploaded.');
    }

    /**
     * Create a link in TXP
     * Usage:
     *  // The second parameter should be an
     *  // array with overrides for
     *  // default keys if necessary
     *  // * linkname: Name of the link
     *  // * linksort: Sort order of the link
     *  // * url:      Url to use on link
     *  // * category: Category to put link into
     *  // * description: Description given to the link
     *  // See https://docs.textpattern.com/administration/links-panel for more info
     *  $I->makeALinkInTxp($I, ['link_name' => 'custom-link-name']);
     *
     * @param Actor $I    Text actor
     * @param array $data Data to pass to assertions
     *
     * @return void
     */
    public function makeALinkInTxp(Actor $I, array $data = [])
    {
        $faker = Factory::create();
        $params = Arrays::defaultAttributes(
            [
                self::KEY_LINKNAME      => implode(' ', $faker->unique()->words(3)),
                self::KEY_LINKSORT      => $faker->unique()->slug,
                self::KEY_URL           => $faker->unique()->url,
                self::KEY_CATEGORY      => "",
                self::KEY_DESCRIPTION   => $faker->unique()->words(3)
            ],
            $data
        );

        $I->amOnPage('/textpattern/index.php?event=link');
        $I->click('Create new link');

        $I->fillField(self::KEY_LINKNAME, $params[self::KEY_LINKNAME]);
        $I->fillField(self::KEY_LINKSORT, $params[self::KEY_LINKSORT]);
        $I->fillField(self::KEY_URL, $params[self::KEY_URL]);
        if ($params[self::KEY_CATEGORY]) {
            $I->selectOption(self::KEY_CATEGORY, $params[self::KEY_CATEGORY]);
        }
        $I->fillField(self::KEY_DESCRIPTION, $params[self::KEY_DESCRIPTION]);
        $I->click('Save');
        $I->waitForText('Link '.$params[self::KEY_LINKNAME].' created.');

    }

    /**
     * Create a post in TXP
     *
     * Usage:
     *  // The second parameter should be an
     *  // array with overrides for
     *  // default keys if necessary
     *  // * title:     The title of the article
     *  // * body:      The body of the article
     *  // * url_title: The url title string
     *  // * status:    The article status
     *  // * section:   The article section
     *  // * category:  The article category
     *  // * extra:     The article extra value
     *  // See https://docs.textpattern.com/administration/write-panel for more info
     *  $I->makeAPostInTxp($I, ['title' => 'Custom Article Title']);
     *
     * @param Actor $I    Text actor
     * @param array $data Data to pass to assertions
     *
     * @return void
     */
    public function makeAPostInTxp(Actor $I, array $data = [])
    {
        $faker = Factory::create();
        $params = Arrays::defaultAttributes(
            [
                self::KEY_TITLE     => implode(' ', $faker->unique()->words(3)),
                self::KEY_BODY      => $faker->unique()->paragraph,
                self::KEY_URL_TITLE => implode('-', $faker->unique()->words(3)),
                self::KEY_STATUS    => 4,
                self::KEY_SECTION   => 'home',
                self::KEY_CATEGORY  => [],
                self::KEY_EXTRA     => []
            ],
            $data
        );

        $I->amOnPage('/textpattern/index.php?event=article');

        // check if the url-title field is visible.
        // If it isn't then click the Meta button to show it
        if (!$I->executeJs('return $("#url-title").is(":visible");')) {
            $I->click('Meta');
        }

        $I->scrollTo(['id' => self::KEY_TITLE]);
        $I->fillField(['id' => self::KEY_TITLE], $params[self::KEY_TITLE]);

        $I->scrollTo(['id' => self::KEY_BODY]);
        $I->fillField(['id' => self::KEY_BODY], $params[self::KEY_BODY]);

        $I->scrollTo(["id" => "status-". $params[self::KEY_STATUS]]);
        $I->checkOption(["id" => "status-". $params[self::KEY_STATUS]]);
        $I->scrollTo(['id' => self::KEY_SECTION]);
        $I->selectOption(['id' => self::KEY_SECTION], $params[self::KEY_SECTION]);
        // if categories were passed then set them as either the
        // multi select rss_uc_multiselect_id
        // or category-1 & category-2
        if (count($params[self::KEY_CATEGORY]) > 0) {
            $multiSelectExists = $I->executeJs(
                'return document.getElementById("rss_uc_multiselect_id").length'
            );
            if (intval($multiSelectExists) === 1) {
                $I->selectOption(
                    ['id' => 'rss_uc_multiselect_id'],
                    $params[self::KEY_CATEGORY]
                );
            } else {
                if (isset($params[self::KEY_CATEGORY][0])) {
                    $I->selectOption(['id' => 'category-1'], $params[self::KEY_CATEGORY][0]);
                };
                if (isset($params[self::KEY_CATEGORY][1])) {
                    $I->selectOption(['id' => 'category-2'], $params[self::KEY_CATEGORY][1]);
                };
            }
        }
        $I->scrollTo(['id' => 'url-title']);
        $I->fillField(['id' => 'url-title'], $params[self::KEY_URL_TITLE]);

        if (count($params[self::KEY_EXTRA]) > 0) {
            foreach ($params[self::KEY_EXTRA] as $key => $value) {
                $I->scrollTo(['id' => $key]);
                $I->fillField(['id' => $key], $value);
            }
        }

        $I->scrollTo('input[name="publish"]');
        $I->click('input[name="publish"]');
        $I->waitForText('Article posted.');

        $url = $I->grabAttributeFrom(['class' => 'article-view'], 'href');
        $I->amOnPage(parse_url($url, PHP_URL_PATH));
        $I->see($params[self::KEY_TITLE]);
    }
}
