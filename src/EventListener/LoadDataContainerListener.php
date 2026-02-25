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

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use ErdmannFreunde\BookBundle\EventListener\DataContainer\BookChildTableListener;
use ErdmannFreunde\BookBundle\EventListener\DataContainer\MissingLanguageIconListener;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Terminal42\ChangeLanguage\EventListener\BackendView\ParentChildViewListener;
use Terminal42\ChangeLanguage\EventListener\DataContainer\ParentTableListener;

#[AsHook('loadDataContainer')]
class LoadDataContainerListener
{
    public function __construct(private readonly ParameterBagInterface $params)
    {
    }

    public function __invoke(string $table): void
    {
        $bundles = (array) $this->params->get('kernel.bundles');

        if (isset($bundles['Terminal42ChangeLanguageBundle'])) {
            switch ($table) {
                case 'tl_book_archive':
                    $listener = new ParentTableListener($table);
                    $listener->register();
                    break;

                case 'tl_book':
                    $listener = new MissingLanguageIconListener();
                    $listener->register($table);

                    $listener = new BookChildTableListener($table);
                    $listener->register();

                    $listener = new ParentChildViewListener($table);
                    $listener->register();
                    break;
            }
        }
    }
}
