<?php
/**
 * @file classes/components/form/publication/PKPTitleAbstractForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTitleAbstractForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's title and abstract
 */

namespace PKP\components\forms\publication;

use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_TITLE_ABSTRACT', 'titleAbstract');

class PKPTitleAbstractForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_TITLE_ABSTRACT;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /** @var Publication */
    public $publication;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Publication $publication The publication to change settings for
     */
    public function __construct($action, $locales, $publication)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->publication = $publication;

        $this->addField(new FieldText('prefix', [
            'label' => __('common.prefix'),
            'description' => __('common.prefixAndTitle.tip'),
            'size' => 'small',
            'isMultilingual' => true,
            'value' => $publication->getData('prefix'),
        ]))
            ->addField(new FieldText('title', [
                'label' => __('common.title'),
                'size' => 'large',
                'isMultilingual' => true,
                'value' => $publication->getData('title'),
            ]))
            ->addField(new FieldText('subtitle', [
                'label' => __('common.subtitle'),
                'size' => 'large',
                'isMultilingual' => true,
                'value' => $publication->getData('subtitle'),
            ]))
            ->addField(new FieldRichTextarea('abstract', [
                'label' => __('common.abstract'),
                'isMultilingual' => true,
                'value' => $publication->getData('abstract'),
            ]));
    }
}
