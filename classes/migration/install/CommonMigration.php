<?php

/**
 * @file classes/migration/install/CommonMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CommonMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CommonMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Describes the installation and upgrade version history for the application and all installed plugins.
        Schema::create('versions', function (Blueprint $table) {
            $table->integer('major')->default(0)->comment('Major component of version number, e.g. the 2 in OJS 2.3.8-0');
            $table->integer('minor')->default(0)->comment('Minor component of version number, e.g. the 3 in OJS 2.3.8-0');
            $table->integer('revision')->default(0)->comment('Revision component of version number, e.g. the 8 in OJS 2.3.8-0');
            $table->integer('build')->default(0)->comment('Build component of version number, e.g. the 0 in OJS 2.3.8-0');
            $table->datetime('date_installed');
            $table->smallInteger('current')->default(0)->comment('1 iff the version entry being described is currently active. This permits the table to store past installation history for forensic purposes.');
            $table->string('product_type', 30)->comment('Describes the type of product this row describes, e.g. "plugins.generic" (for a generic plugin) or "core" for the application itelf')->nullable();
            $table->string('product', 30)->comment('Uniquely identifies the product this version row describes, e.g. "ojs2" for OJS 2.x, "languageToggle" for the language toggle block plugin, etc.')->nullable();
            $table->string('product_class_name', 80)->comment('Specifies the class name associated with this product, for plugins, or the empty string where not applicable.')->nullable();
            $table->smallInteger('lazy_load')->default(0)->comment('1 iff the row describes a lazy-load plugin; 0 otherwise');
            $table->smallInteger('sitewide')->default(0)->comment('1 iff the row describes a site-wide plugin; 0 otherwise');
            $table->unique(['product_type', 'product', 'major', 'minor', 'revision', 'build'], 'versions_pkey');
        });

        // Common site settings.
        Schema::create('site', function (Blueprint $table) {
            $table->bigInteger('redirect')->default(0)->comment('If not 0, redirect to the specified journal/conference/... site.');
            $table->string('primary_locale', 14)->comment('Primary locale for the site.');
            $table->smallInteger('min_password_length')->default(6);
            $table->string('installed_locales', 1024)->default('en_US')->comment('Locales for which support has been installed.');
            $table->string('supported_locales', 1024)->comment('Locales supported by the site (for hosted journals/conferences/...).')->nullable();
            $table->string('original_style_file_name', 255)->nullable();
        });

        // Site settings.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('setting_name', 255);
            $table->string('locale', 14)->default('');
            $table->mediumText('setting_value')->nullable();
            $table->unique(['setting_name', 'locale'], 'site_settings_pkey');
        });

        // User authentication sources.
        Schema::create('auth_sources', function (Blueprint $table) {
            $table->bigInteger('auth_id')->autoIncrement();
            $table->string('title', 60);
            $table->string('plugin', 32);
            $table->smallInteger('auth_default')->default(0);
            $table->text('settings')->nullable();
        });

        // User authentication credentials and profile data.
        Schema::create('users', function (Blueprint $table) {
            $table->bigInteger('user_id')->autoIncrement();
            $table->string('username', 32);
            $table->string('password', 255);
            $table->string('email', 255);
            $table->string('url', 2047)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mailing_address', 255)->nullable();
            $table->string('billing_address', 255)->nullable();
            $table->string('country', 90)->nullable();
            $table->string('locales', 255)->default('[]');
            $table->text('gossip')->nullable();
            $table->datetime('date_last_email')->nullable();
            $table->datetime('date_registered');
            $table->datetime('date_validated')->nullable();
            $table->datetime('date_last_login')->nullable();
            $table->smallInteger('must_change_password')->nullable();
            $table->bigInteger('auth_id')->nullable();
            $table->string('auth_str', 255)->nullable();
            $table->smallInteger('disabled')->default(0);
            $table->text('disabled_reason')->nullable();
            $table->smallInteger('inline_help')->nullable();
            $table->unique(['username'], 'users_username');
            $table->unique(['email'], 'users_email');
        });

        // Locale-specific user data
        Schema::create('user_settings', function (Blueprint $table) {
            $table->bigInteger('user_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->index(['user_id'], 'user_settings_user_id');
            $table->unique(['user_id', 'locale', 'setting_name'], 'user_settings_pkey');
            $table->index(['setting_name', 'locale'], 'user_settings_locale_setting_name_index');
        });

        // Browser/user sessions and session data.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('session_id', 128);
            $table->bigInteger('user_id')->nullable();
            $table->string('ip_address', 39);
            $table->string('user_agent', 255)->nullable();
            $table->bigInteger('created')->default(0);
            $table->bigInteger('last_used')->default(0);
            $table->smallInteger('remember')->default(0);
            $table->text('data');
            $table->string('domain', 255)->nullable();
            $table->index(['user_id'], 'sessions_user_id');
            $table->unique(['session_id'], 'sessions_pkey');
        });

        // Access keys are used to provide pseudo-login functionality for security-minimal tasks. Passkeys can be emailed directly to users, who can use them for a limited time in lieu of standard username and password.
        Schema::create('access_keys', function (Blueprint $table) {
            $table->bigInteger('access_key_id')->autoIncrement();
            $table->string('context', 40);
            $table->string('key_hash', 40);
            $table->bigInteger('user_id');
            $table->bigInteger('assoc_id')->nullable();
            $table->datetime('expiry_date');
            $table->index(['key_hash', 'user_id', 'context'], 'access_keys_hash');
        });

        // Stores notifications for users as created by the system after certain operations.
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigInteger('notification_id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('level');
            $table->bigInteger('type');
            $table->datetime('date_created');
            $table->datetime('date_read')->nullable();
            $table->bigInteger('assoc_type')->nullable();
            $table->bigInteger('assoc_id')->nullable();
            $table->index(['context_id', 'user_id', 'level'], 'notifications_context_id_user_id');
            $table->index(['context_id', 'level'], 'notifications_context_id');
            $table->index(['assoc_type', 'assoc_id'], 'notifications_assoc');
            $table->index(['user_id', 'level'], 'notifications_user_id_level');
        });

        // Stores metadata for specific notifications
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->bigInteger('notification_id');
            $table->string('locale', 14)->nullable();
            $table->string('setting_name', 64);
            $table->mediumText('setting_value');
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
            $table->unique(['notification_id', 'locale', 'setting_name'], 'notification_settings_pkey');
            $table->foreign('notification_id')->references('notification_id')->on('notifications')->onDelete('cascade');
        });

        // Stores user preferences on what notifications should be blocked and/or emailed to them
        Schema::create('notification_subscription_settings', function (Blueprint $table) {
            $table->bigInteger('setting_id')->autoIncrement();
            $table->string('setting_name', 64);
            $table->mediumText('setting_value');
            $table->bigInteger('user_id');
            $table->bigInteger('context');
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
        });

        // Stores subscriptions to the notification mailing list
        Schema::create('notification_mail_list', function (Blueprint $table) {
            $table->bigInteger('notification_mail_list_id')->autoIncrement();
            $table->string('email', 90);
            $table->smallInteger('confirmed')->default(0);
            $table->string('token', 40);
            $table->bigInteger('context');
            $table->unique(['email', 'context'], 'notification_mail_list_email_context');
        });

        // Default email templates.
        Schema::create('email_templates_default', function (Blueprint $table) {
            $table->bigInteger('email_id')->autoIncrement();
            $table->string('email_key', 255)->comment('Unique identifier for this email.');
            $table->smallInteger('can_disable')->default(0);
            $table->smallInteger('can_edit')->default(0);
            $table->bigInteger('from_role_id')->nullable();
            $table->bigInteger('to_role_id')->nullable();
            $table->bigInteger('stage_id')->nullable();
            $table->index(['email_key'], 'email_templates_default_email_key');
        });

        // Default data for email templates.
        Schema::create('email_templates_default_data', function (Blueprint $table) {
            $table->string('email_key', 255)->comment('Unique identifier for this email.');
            $table->string('locale', 14)->default('en_US');
            $table->string('subject', 255);
            $table->text('body')->nullable();
            $table->text('description')->nullable();
            $table->unique(['email_key', 'locale'], 'email_templates_default_data_pkey');
        });

        // Templates for emails.
        Schema::create('email_templates', function (Blueprint $table) {
            $table->bigInteger('email_id')->autoIncrement();
            $table->string('email_key', 255)->comment('Unique identifier for this email.');
            $table->bigInteger('context_id');
            $table->smallInteger('enabled')->default(1);
            $table->unique(['email_key', 'context_id'], 'email_templates_email_key');
        });

        Schema::create('email_templates_settings', function (Blueprint $table) {
            $table->bigInteger('email_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->index(['email_id'], 'email_settings_email_id');
            $table->unique(['email_id', 'locale', 'setting_name'], 'email_settings_pkey');
        });

        // Association between Mailables and Email Templates
        Schema::create('mailable_templates', function (Blueprint $table) {
            $table->bigInteger('email_id');
            $table->string('mailable_id', 255);
            $table->foreign('email_id')->references('email_id')->on('email_templates');
            $table->primary(['email_id', 'mailable_id']);
        });

        // Resumption tokens for the OAI protocol interface.
        Schema::create('oai_resumption_tokens', function (Blueprint $table) {
            $table->string('token', 32);
            $table->bigInteger('expire');
            $table->integer('record_offset');
            $table->text('params')->nullable();
            $table->unique(['token'], 'oai_resumption_tokens_pkey');
        });

        // Plugin settings.
        Schema::create('plugin_settings', function (Blueprint $table) {
            $table->string('plugin_name', 80);
            $table->bigInteger('context_id');
            $table->string('setting_name', 80);
            $table->mediumText('setting_value')->nullable();
            $table->string('setting_type', 6)->comment('(bool|int|float|string|object)');
            $table->index(['plugin_name'], 'plugin_settings_plugin_name');
            $table->unique(['plugin_name', 'context_id', 'setting_name'], 'plugin_settings_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('plugin_settings');
        Schema::drop('oai_resumption_tokens');
        Schema::drop('email_templates_settings');
        Schema::drop('email_templates');
        Schema::drop('email_templates_default_data');
        Schema::drop('email_templates_default');
        Schema::drop('mailable_templates');
        Schema::drop('notification_mail_list');
        Schema::drop('notification_subscription_settings');
        Schema::drop('notification_settings');
        Schema::drop('notifications');
        Schema::drop('access_keys');
        Schema::drop('sessions');
        Schema::drop('user_settings');
        Schema::drop('users');
        Schema::drop('auth_sources');
        Schema::drop('site_settings');
        Schema::drop('site');
        Schema::drop('versions');
    }
}
