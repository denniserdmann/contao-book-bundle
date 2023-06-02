<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\Models;

use Contao\Date;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;

/**
 * Reads and writes book items.
 */
class BookModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_book';

    /**
     * Find a published book item from one or more book archives by its ID or alias.
     *
     * @param mixed $varId      The numeric ID or alias name
     * @param array $arrPids    An array of parent IDs
     * @param array $arrOptions An optional options array
     *
     * @return BookModel|null The model or null if there are no book items
     */
    public static function findPublishedByParentAndIdOrAlias($varId, array $arrPids, array $arrOptions = []): ?self
    {
        if (empty($arrPids) || !\is_array($arrPids)) {
            return null;
        }

        $t = static::$strTable;
        $arrColumns = !preg_match('/^[1-9]\d*$/', $varId) ? ["BINARY $t.alias=?"] : ["$t.id=?"];
        $arrColumns[] = "$t.pid IN(".implode(',', array_map('\intval', $arrPids)).')';

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        return static::findOneBy($arrColumns, $varId, $arrOptions);
    }

    /**
     * Find published book items by their parent ID.
     *
     * @param array     $arrPids     An array of book archive IDs
     * @param bool|null $blnFeatured If true, return only featured book items, if false, return only unfeatured book items
     * @param int       $intLimit    An optional limit
     * @param int       $intOffset   An optional offset
     * @param array     $arrOptions  An optional options array
     *
     * @return Collection|BookModel[]|BookModel|null A collection of models or null if there are no book items
     */
    public static function findPublishedByPids(array $arrPids, bool $blnFeatured = null, $intLimit = 0, $intOffset = 0, array $arrOptions = [], array $arrCategories = [])
    {
        if (empty($arrPids) || !\is_array($arrPids)) {
            return null;
        }

        $t = static::$strTable;
        $arrColumns = ["$t.pid IN(".implode(',', array_map('\intval', $arrPids)).')'];

        if (true === $blnFeatured) {
            $arrColumns[] = "$t.featured='1'";
        } elseif (false === $blnFeatured) {
            $arrColumns[] = "$t.featured=''";
        }

        if (!BE_USER_LOGGED_IN || TL_MODE === 'BE') {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.endDate DESC";
        }

        // check if categories are selected and filter by them
        // not working because $t.categories is still a serialized array
        if ($arrCategories) {
            $stringCategories = StringUtil::deserialize($arrCategories);
            $arrColumns[] = "$t.categories LIKE '%\"".implode("\"%' OR $t.categories LIKE '%\"", array_map('\intval', $stringCategories))."\"%'";
        }

        $arrOptions['limit'] = $intLimit;
        $arrOptions['offset'] = $intOffset;

        return static::findBy($arrColumns, null, $arrOptions);
    }

    /**
     * Count published book items by their parent ID.
     *
     * @param array     $arrPids     An array of book archive IDs
     * @param bool|null $blnFeatured If true, return only featured book items, if false, return only unfeatured book items
     * @param array     $arrOptions  An optional options array
     *
     * @return int The number of book items
     */
    public static function countPublishedByPids(array $arrPids, bool $blnFeatured = null, array $arrCategories = [], array $arrOptions = []): int
    {
        if (empty($arrPids) || !\is_array($arrPids)) {
            return 0;
        }

        $t = static::$strTable;
        $arrColumns = ["$t.pid IN(".implode(',', array_map('\intval', $arrPids)).')'];

        if (true === $blnFeatured) {
            $arrColumns[] = "$t.featured='1'";
        } elseif (false === $blnFeatured) {
            $arrColumns[] = "$t.featured=''";
        }

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        // check if categories are selected and filter by them
        if ($arrCategories) {
            $stringCategories = StringUtil::deserialize($arrCategories);
            $arrColumns[] = "$t.categories LIKE '%\"".implode("\"%' OR $t.categories LIKE '%\"", array_map('\intval', $stringCategories))."\"%'";
        }

        return static::countBy($arrColumns, null, $arrOptions);
    }

    /**
     * Find published book items by their parent ID.
     *
     * @param int   $intId      The book archive ID
     * @param int   $intLimit   An optional limit
     * @param array $arrOptions An optional options array
     *
     * @return Collection|BookModel[]|BookModel|null A collection of models or null if there are no book items
     */
    public static function findPublishedByPid(int $intId, int $intLimit = 0, array $arrOptions = [])
    {
        $t = static::$strTable;
        $arrColumns = ["$t.pid=?"];

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.endDate DESC";
        }

        if ($intLimit > 0) {
            $arrOptions['limit'] = $intLimit;
        }

        return static::findBy($arrColumns, $intId, $arrOptions);
    }

    /**
     * Find published book items with the default redirect target by their parent ID.
     *
     * @param int   $intPid     The book archive ID
     * @param array $arrOptions An optional options array
     *
     * @return Collection|BookModel[]|BookModel|null A collection of models or null if there are no book items
     */
    public static function findPublishedDefaultByPid(int $intPid, array $arrOptions = [])
    {
        $t = static::$strTable;
        $arrColumns = ["$t.pid=? AND $t.source='default'"];

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.endDate DESC";
        }

        return static::findBy($arrColumns, $intPid, $arrOptions);
    }
}
