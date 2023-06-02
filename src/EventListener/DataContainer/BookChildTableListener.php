<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\EventListener\DataContainer;

use Contao\Model;
use Contao\Model\Collection;
use ErdmannFreunde\BookBundle\Models\BookModel;
use Terminal42\ChangeLanguage\EventListener\DataContainer\AbstractChildTableListener;

class BookChildTableListener extends AbstractChildTableListener
{
    protected function getTitleField(): string
    {
        return 'title';
    }

    protected function getSorting(): string
    {
        return 'sorting';
    }

    /**
     * @param BookModel             $current
     * @param Collection<BookModel> $models
     */
    protected function formatOptions(Model $current, Collection $models): array
    {
        $options = [];

        foreach ($models as $model) {
            $options[$model->id] = sprintf('%s [ID %s]', $model->headline, $model->id);
        }

        return $options;
    }
}
