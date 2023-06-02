<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use ErdmannFreunde\BookBundle\Classes\Book;
use ErdmannFreunde\BookBundle\Models\BookModel;

/**
 * @Hook("replaceInsertTags")
 */
class InsertTagsListener
{
    private const SUPPORTED_TAGS = ['book_url'];
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function __invoke(string $insertTag, bool $useCache, string $cachedValue, array $flags, array $tags, array $cache, int $_rit, int $_cnt)
    {
        $elements = explode('::', $insertTag);
        $key = strtolower($elements[0]);

        if (\in_array($key, self::SUPPORTED_TAGS, true)) {
            return $this->replaceInsertTags($key, $elements[1], $flags);
        }

        return false;
    }

    private function replaceInsertTags(string $insertTag, string $idOrAlias, array $flags): string
    {
        $this->framework->initialize();

        /** @var BookModel $adapter */
        $adapter = $this->framework->getAdapter(BookModel::class);
        $book = $adapter->findByIdOrAlias($idOrAlias);

        if (null === $book) {
            return '';
        }

        if ('book_url' === $insertTag) {
            /** @var Book $adapter */
            $adapter = $this->framework->getAdapter(Book::class);

            return $adapter->generateBookUrl($book, false, \in_array('absolute', $flags, true));
        }

        return '';
    }
}
