<?php
/**
 * @file classes/emailTemplate/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage email templates.
 */

namespace PKP\emailTemplate;

use APP\emailTemplate\DAO;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    // The name of the class to map this entity to its schema
    public string $schemaMap = maps\Schema::class;

    protected PKPRequest $request;

    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, PKPRequest $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Emailtemplate
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function getByKey(int $contextId, string $key): ?EmailTemplate
    {
        return $this->dao->getByKey($contextId, $key);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an email template
     *
     * Perform validation checks on data used to add or edit an email template.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?EmailTemplate $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE),
            $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE),
            $allowedLocales,
            $primaryLocale
        );

        // Validate fields only when adding a template
        if (!$object) {

            // Require a context id
            $validator->after(function ($validator) use ($props) {
                if (!isset($props['contextId'])) {
                    $validator->errors()->add('contextId', __('manager.emails.emailTemplate.contextRequired'));
                }
            });

            // Don't allow duplicate keys in the same context
            $validator->after(function ($validator) use ($props) {
                if (!isset($props['contextId'])) {
                    return;
                }
                $existingEmailTemplate = $this->getByKey($props['contextId'], $props['key']);
                if (!empty($existingEmailTemplate) && !empty($existingEmailTemplate->getData('id'))) {
                    $validator->errors()->add('key', __('manager.emails.emailTemplate.noDuplicateKeys'));
                }
            });
        }

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('EmailTemplate::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(EmailTemplate $emailTemplate): int
    {
        $id = $this->dao->insert($emailTemplate);

        Hook::call('EmailTemplate::add', [$emailTemplate]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(EmailTemplate $emailTemplate, array $params)
    {
        $newEmailTemplate = clone $emailTemplate;
        $newEmailTemplate->setAllData(array_merge($newEmailTemplate->_data, $params));

        Hook::call('EmailTemplate::edit', [$newEmailTemplate, $emailTemplate, $params]);

        if ($newEmailTemplate->getId()) {
            $this->dao->update($newEmailTemplate);
        } else {
            $this->dao->insert($newEmailTemplate);
        }
    }

    /** @copydoc DAO::delete() */
    public function delete(EmailTemplate $emailTemplate)
    {
        Hook::call('EmailTemplate::delete::before', [&$emailTemplate]);
        $this->dao->delete($emailTemplate);
        Hook::call('EmailTemplate::delete', [&$emailTemplate]);
    }

    /**
     * Delete a collection of email templates
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $emailTemplate) {
            $this->delete($emailTemplate);
        }
    }

    /**
     * Remove all custom templates and template modifications. Resets the
     * email template settings to their installed defaults.
     *
     * @return array List of keys that were deleted or reset
     */
    public function restoreDefaults($contextId): array
    {
        $results = $this->getCollector()
            ->filterByContext($contextId)
            ->filterByIsModified(true)
            ->getMany();

        $deletedKeys = [];
        $results->each(function ($emailTemplate) use ($deletedKeys) {
            $deletedKeys[] = $emailTemplate->getData('key');
            $this->delete($emailTemplate);
        });
        Hook::call('EmailTemplate::restoreDefaults', [&$deletedKeys, $contextId]);
        return $deletedKeys;
    }
}
