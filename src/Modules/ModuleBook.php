<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\Modules;

use Contao\ContentModel;
use Contao\Date;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Module;
use Contao\StringUtil;
use ErdmannFreunde\BookBundle\Classes\Book;
use ErdmannFreunde\BookBundle\Models\BookArchiveModel;
use ErdmannFreunde\BookBundle\Models\BookCategoryModel;

/**
 * Class ModuleBook.
 *
 * Parent class for book modules.
 */
abstract class ModuleBook extends Module
{
    /**
     * Sort out protected archives.
     */
    protected function sortOutProtected(array $arrArchives): array
    {
        if (empty($arrArchives) || !\is_array($arrArchives)) {
            return $arrArchives;
        }

        $this->import(FrontendUser::class, 'User');
        $objArchive = BookArchiveModel::findMultipleByIds($arrArchives);
        $arrArchives = [];

        if (null !== $objArchive) {
            while ($objArchive->next()) {
                if ($objArchive->protected) {
                    if (!FE_USER_LOGGED_IN || !\is_array($this->User->groups)) {
                        continue;
                    }

                    $groups = StringUtil::deserialize($objArchive->groups);

                    if (empty($groups) || !\is_array($groups) || !\count(array_intersect($groups, $this->User->groups))) {
                        continue;
                    }
                }

                $arrArchives[] = $objArchive->id;
            }
        }

        return $arrArchives;
    }

    /**
     * Parse an item and return it as string.
     *
     * @param BookModel $objItem
     * @param bool           $blnAddArchive
     * @param mixed          $strClass
     * @param mixed          $intCount
     *
     * @throws \Exception
     */
    protected function parseItem($objItem, $blnAddArchive = false, $strClass = '', $intCount = 0): string
    {
        global $objPage;

        $objTemplate = new FrontendTemplate($this->book_template);
        $objTemplate->setData($objItem->row());

        $objTemplate->class = ('' !== $objItem->cssClass ? ' '.$objItem->cssClass : '').$strClass;
        $objTemplate->headline = $objItem->title;
        $objTemplate->linkHeadline = $this->generateLink($objItem->headline, $objItem, $blnAddArchive);
        $objTemplate->more = $this->generateLink($GLOBALS['TL_LANG']['MSC']['more'], $objItem, $blnAddArchive, true);
        $objTemplate->link = Book::generateBookUrl($objItem, $blnAddArchive);
        $objTemplate->count = $intCount; // see #5708
        $objTemplate->text = '';
        $objTemplate->hasText = false;
        $objTemplate->hasTeaser = false;

        // Clean the RTE output
        if ($objItem->teaser) {
            $objTemplate->hasTeaser = true;
            $objTemplate->teaser = StringUtil::toHtml5($objItem->teaser);
            $objTemplate->teaser = StringUtil::encodeEmail($objTemplate->teaser);
        }

        // Display the "read more" button for external/article links
        if ('default' !== $objItem->source) {
            $objTemplate->text = true;
        } // Compile the book text
        else {
            $objElement = ContentModel::findPublishedByPidAndTable($objItem->id, 'tl_book');

            if (null !== $objElement) {
                while ($objElement->next()) {
                    $objTemplate->text .= self::getContentElement($objElement->current());
                }
            }

            $objTemplate->hasText = static fn () => ContentModel::countPublishedByPidAndTable($objItem->id, 'tl_book') > 0;
        }

        // Add the meta information
        if ($objItem->startDate && $objItem->endDate) 
        {
            $objTemplate->date = Date::parse($objPage->dateFormat, $objItem->startDate) . ' – ' . Date::parse($objPage->dateFormat, $objItem->endDate);
        } elseif($objItem->startDate) {
            $objTemplate->date = Date::parse($objPage->dateFormat, $objItem->startDate) . ' – heute';
        } else {
            $objTemplate->date = Date::parse($objPage->dateFormat, $objItem->endDate);
        }

        $objTemplate->timestamp = $objItem->endDate;

        if ($objItem->categories) {
            $objTemplate->categories = '';
            $objCategories = [];
            $objTemplate->category_models = [];
            $categories = StringUtil::deserialize($objItem->categories);

            foreach ($categories as $category) {
                $objBookCategoryModel = BookCategoryModel::findByPk($category);
                $objTemplate->category_models[] = $objBookCategoryModel;
                $objCategories[] = $objBookCategoryModel->alias;

                if (!$objTemplate->category_titles) {
                    $objTemplate->category_titles = '<ul class="level_1"><li>'.$objBookCategoryModel->title.'</li>';
                } else {
                    $objTemplate->category_titles .= '<li>'.$objBookCategoryModel->title.'</li>';
                }
            }
            $objTemplate->category_titles .= '</ul>';
            $objTemplate->categories .= implode(',', $objCategories);
        }

        $objTemplate->addImage = false;

        // Add an image
        if ($objItem->addImage && '' !== $objItem->singleSRC) {
            $objModel = FilesModel::findByUuid($objItem->singleSRC);

            if (null !== $objModel && is_file(TL_ROOT.'/'.$objModel->path)) {
                // Do not override the field now that we have a model registry (see #6303)
                $arrArticle = $objItem->row();

                // Override the default image size
                if ('' !== $this->imgSize) {
                    $size = StringUtil::deserialize($this->imgSize);

                    if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2])) {
                        $arrArticle['size'] = $this->imgSize;
                    }
                }

                $arrArticle['singleSRC'] = $objModel->path;
                $this->addImageToTemplate($objTemplate, $arrArticle, null, null, $objModel);

                // Link to the book reader if no image link has been defined (see #30)
                if (!$objTemplate->fullsize && !$objTemplate->imageUrl && $objTemplate->text) {
                    // Unset the image title attribute
                    $picture = $objTemplate->picture;
                    unset($picture['title']);
                    $objTemplate->picture = $picture;

                    // Link to the book reader
                    $objTemplate->href = $objTemplate->link;
                    $objTemplate->linkTitle = StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $objItem->headline), true);

                    // If the external link is opened in a new window, open the image link in a new window, too
                    if ('external' === $objTemplate->source && $objTemplate->target) {
                        $objTemplate->attributes .= ' target="_blank"';
                    }
                }
            }
        }

        return $objTemplate->parse();
    }

    /**
     * Parse one or more items and return them as array.
     *
     * @param BookModel $objArticles
     * @param bool           $blnAddArchive
     *
     * @throws \Exception
     */
    protected function parseItems($objArticles, $blnAddArchive = false): array
    {
        $limit = $objArticles->count();

        if ($limit < 1) {
            return [];
        }

        $count = 0;
        $arrArticles = [];
        $uuids = [];

        foreach ($objArticles as $objArticle) {
            if ($objArticle->addImage && $objArticle->singleSRC) {
                $uuids[] = $objArticle->singleSRC;
            }
        }

        // Preload all images in one query so they are loaded into the model registry
        FilesModel::findMultipleByUuids($uuids);

        foreach ($objArticles as $objArticle) {
            $arrArticles[] = $this->parseItem($objArticle, $blnAddArchive, (1 === ++$count ? ' first' : '').($count === $limit ? ' last' : '').(0 === $count % 2 ? ' odd' : ' even'), $count);
        }

        return $arrArticles;
    }

    /**
     * Generate a link and return it as string.
     *
     * @param mixed $strLink
     * @param mixed $objItem
     * @param mixed $blnAddArchive
     * @param mixed $blnIsReadMore
     *
     * @throws \Exception
     */
    protected function generateLink($strLink, $objItem, $blnAddArchive = false, $blnIsReadMore = false): string
    {
        // Internal link
        if ('external' !== $objItem->source) {
            return sprintf(
                '<a href="%s" title="%s">%s%s</a>',
                Book::generateBookUrl($objItem, $blnAddArchive),
                StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['readMore'], $objItem->headline), true),
                $strLink,
                ($blnIsReadMore ? ' <span class="invisible">'.$objItem->headline.'</span>' : '')
            );
        }

        // Ampersand URIs
        $strArticleUrl = StringUtil::ampersand($objItem->url);

        global $objPage;

        $attributes = '';

        if ($objItem->target) {
            $attributes = ('xhtml' === $objPage->outputFormat ? ' onclick="return !window.open(this.href)"' : ' target="_blank"');
        }

        // External link
        return sprintf(
            '<a href="%s" title="%s"%s>%s</a>',
            $strArticleUrl,
            StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['open'], $strArticleUrl)),
            $attributes,
            $strLink
        );
    }
}
