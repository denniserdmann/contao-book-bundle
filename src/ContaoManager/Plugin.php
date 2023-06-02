<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use ErdmannFreunde\BookBundle\ErdmannFreundeBookBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(ErdmannFreundeBookBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
                ->setReplace(['book']),
        ];
    }
}
