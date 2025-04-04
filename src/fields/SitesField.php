<?php

namespace goldinteractive\sitesfield\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQueryInterface;
use craft\fields\data\MultiOptionsFieldData;
use craft\fields\data\OptionData;
use craft\fields\data\SingleOptionFieldData;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use yii\db\Schema;


class SitesField extends Field
{
    /**
     * @var int|null
     */
    public ?int $maxOptions = null;

    public static function displayName(): string
    {
        return Craft::t('sites-field', 'Sites Field');
    }

    public static function valueType(): string
    {
        return 'mixed';
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['maxOptions'], 'number', 'integerOnly' => true];

        return $rules;
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('sites-field/settings.twig', ['field' => $this,]);
    }

    public function getContentColumnType(): array|string
    {
        return Schema::TYPE_TEXT;
    }

    public function normalizeValue(mixed $value, ElementInterface $element = null): mixed
    {
        if ($value instanceof MultiOptionsFieldData || $value instanceof SingleOptionFieldData) {
            return $value;
        }

        if (is_string($value) && (
                str_starts_with($value, '[') ||
                str_starts_with($value, '{')
            )) {
            $value = json_decode($value);

            if (!is_array($value)) {
                $value = [];
            }
        }elseif (is_string($value)) {
            $value = [$value];
        } elseif (!is_array($value)){
            $value = [];
        }

        if (!empty($value) && $this->maxOptions) {
            $value = array_slice($value, 0, $this->maxOptions);
        }

        $selectedValues = [];
        foreach ((array)$value as $val) {
            $val = (int)$val;

            $selectedValues[] = $val;
        }

        $options = [];
        $optionValues = [];
        $optionLabels = [];
        foreach ($this->options() as $option) {
            $selected = in_array((int)$option['value'], $selectedValues, true);
            $options[] = new OptionData($option['label'], $option['value'], $selected, true);
            $optionValues[] = (int)$option['value'];
            $optionLabels[] = $option['label'];
        }

        if ($this->isRadio()) {
            $value = new SingleOptionFieldData(null, null, true, true);

            if (!empty($selectedValues)) {
                // Convert the value to a SingleOptionFieldData object
                $selectedValue = reset($selectedValues);
                $index = array_search($selectedValue, $optionValues, true);
                $valid = $index !== false;
                $label = $valid ? $optionLabels[$index] : null;
                $value = new SingleOptionFieldData($label, $selectedValue, true, $valid);
            }
        }else {
            $selectedOptions = [];

            foreach ($selectedValues as $selectedValue) {
                $index = array_search($selectedValue, $optionValues, true);
                $valid = $index !== false;
                $label = $valid ? $optionLabels[$index] : null;
                $selectedOptions[] = new OptionData($label, $selectedValue, true, $valid);
            }
            $value = new MultiOptionsFieldData($selectedOptions);
        }

        $value->setOptions($options);

        return $value;
    }

    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if ($value instanceof MultiOptionsFieldData) {
            $serialized = [];
            foreach ($value as $selectedValue) {
                /** @var OptionData $selectedValue */
                $serialized[] = $selectedValue->value;
            }

            return $serialized;
        }

        return parent::serializeValue($value, $element);
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        if ($value instanceof MultiOptionsFieldData) {
            if (ArrayHelper::contains($value, 'valid', false, true)) {
                Craft::$app->getView()->setInitialDeltaValue($this->handle, null);
            }
        } else {
            /** @var SingleOptionFieldData $value */
            if (!$value->valid) {
                Craft::$app->getView()->setInitialDeltaValue($this->handle, null);
            }
        }

        $template = $this->isRadio() ? 'radioGroup' : 'checkboxGroup';

        $values = [];

        if ($value instanceof MultiOptionsFieldData) {
            foreach ($value as $item) {
                $values[] = $item->value;
            }
        }

        return Craft::$app->getView()->renderTemplate('_includes/forms/' . $template . '.twig', [
            'describedBy' => $this->describedBy,
            'name'        => $this->handle,
            'values'      => $values,
            'value'       => $value instanceof SingleOptionFieldData ? $value->value : null,
            'options'     => $this->options(),
        ]);
    }

    public function getElementValidationRules(): array
    {
        $rules = parent::getElementValidationRules();
        $rules[] = 'validateSites';

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function validateSites(): array
    {
        $range = [];

        foreach ($this->options() as $option) {
            $range[] = (int)$option['value'];
        }

        $allowArray = !$this->isRadio();

        return [
            ['in', 'range' => $range, 'allowArray' => $allowArray],
        ];
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return StringHelper::toString($value, ' ');
    }

    public function getElementConditionRuleType(): array|string|null
    {
        return null;
    }

    public function modifyElementsQuery(ElementQueryInterface $query, mixed $value): void
    {
        parent::modifyElementsQuery($query, $value);
    }

    protected function options(bool $encode = false): array
    {
        $ret = [];
        $sites = Craft::$app->getSites()->getAllSites();

        foreach ($sites as $site) {
            $ret[] = [
                'label' => $site->getName(),
                'value' => $encode ? $this->encodeValue($site->id) : $site->id,
            ];
        }

        return $ret;
    }

    protected function isRadio() {
        return $this->maxOptions && $this->maxOptions == 1;
    }
}
