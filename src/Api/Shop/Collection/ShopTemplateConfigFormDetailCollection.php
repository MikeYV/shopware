<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Shop\Struct\ShopTemplateConfigFormDetailStruct;

class ShopTemplateConfigFormDetailCollection extends ShopTemplateConfigFormBasicCollection
{
    /**
     * @var ShopTemplateConfigFormDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): ShopTemplateConfigFormBasicCollection
    {
        return new ShopTemplateConfigFormBasicCollection(
            $this->fmap(function (ShopTemplateConfigFormDetailStruct $shopTemplateConfigForm) {
                return $shopTemplateConfigForm->getParent();
            })
        );
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopTemplateConfigFormDetailStruct $shopTemplateConfigForm) {
                return $shopTemplateConfigForm->getShopTemplate();
            })
        );
    }

    public function getFieldUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getFields()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        $collection = new ShopTemplateConfigFormFieldBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getFields()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormDetailStruct::class;
    }
}