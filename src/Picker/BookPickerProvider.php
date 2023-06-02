<?php

declare(strict_types=1);

/*
 * Contao Book Bundle for Contao Open Source CMS.
 * @copyright  Copyright (c) Erdmann & Freunde
 * @author     Erdmann & Freunde <https://erdmann-freunde.de>
 * @license    MIT
 * @link       http://github.com/erdmannfreunde/contao-book-bundle
 */

namespace ErdmannFreunde\BookBundle\Picker;

use Contao\CoreBundle\Picker\AbstractInsertTagPickerProvider;
use Contao\CoreBundle\Picker\DcaPickerProviderInterface;
use Contao\CoreBundle\Picker\PickerConfig;
use Knp\Menu\FactoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookPickerProvider extends AbstractInsertTagPickerProvider implements DcaPickerProviderInterface
{
    private Security $security;

    public function __construct(FactoryInterface $menuFactory, RouterInterface $router, ?TranslatorInterface $translator, Security $security)
    {
        parent::__construct($menuFactory, $router, $translator);

        $this->security = $security;
    }

    public function getName(): string
    {
        return 'bookPicker';
    }

    public function supportsContext($context): bool
    {
        return \in_array($context, ['book', 'link'], true) && $this->security->isGranted('contao_user.modules', 'book');
    }

    public function supportsValue(PickerConfig $config): bool
    {
        if ('book' === $config->getContext()) {
            return is_numeric($config->getValue());
        }

        return $this->isMatchingInsertTag($config);
    }

    public function getDcaTable(): string
    {
        return 'tl_book';
    }

    public function getDcaAttributes(PickerConfig $config): array
    {
        $value = $config->getValue();
        $attributes = ['fieldType' => 'radio'];

        if ('book' === $config->getContext()) {
            if ($fieldType = $config->getExtra('fieldType')) {
                $attributes['fieldType'] = $fieldType;
            }

            if ($value) {
                $attributes['value'] = array_map('intval', explode(',', $value));
            }

            return $attributes;
        }

        if ($source = $config->getExtra('source')) {
            $attributes['preserveRecord'] = $source;
        }

        if ($this->supportsValue($config)) {
            $attributes['value'] = $this->getInsertTagValue($config);
        }

        return $attributes;
    }

    public function convertDcaValue(PickerConfig $config, $value): string
    {
        if ('book' === $config->getContext()) {
            return (string) $value;
        }

        return sprintf($this->getInsertTag($config), $value);
    }

    protected function getRouteParameters(PickerConfig $config = null): array
    {
        return ['do' => 'book'];
    }

    protected function getDefaultInsertTag(): string
    {
        return '{{book_url::%s}}';
    }
}
