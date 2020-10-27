<?php

/**
 * @file classes/migration/upgrade/PKPv3_3_0UpgradeMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class PKPv3_3_0UpgradeMigration extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {
		Capsule::schema()->table('submissions', function (Blueprint $table) {
			// pkp/pkp-lib#3572 Remove OJS 2.x upgrade tools
			$table->dropColumn('locale');
			// pkp/pkp-lib#6285 submissions.section_id in OMP appears only from 3.2.1
			if (Capsule::schema()->hasColumn($table->getTable(), 'section_id')) {
				// pkp/pkp-lib#2493 Remove obsolete columns
				$table->dropColumn('section_id');
			};
		});
		Capsule::schema()->table('publication_settings', function (Blueprint $table) {
			// pkp/pkp-lib#6096 DB field type TEXT is cutting off long content
			$table->mediumText('setting_value')->nullable()->change();
		});
		Capsule::schema()->table('authors', function (Blueprint $table) {
			// pkp/pkp-lib#2493 Remove obsolete columns
			$table->dropColumn('submission_id');
		});
		Capsule::schema()->table('author_settings', function (Blueprint $table) {
			// pkp/pkp-lib#2493 Remove obsolete columns
			$table->dropColumn('setting_type');
		});
		Capsule::schema()->table('announcements', function (Blueprint $table) {
			// pkp/pkp-lib#5865 Change announcement expiry format in database
			$table->date('date_expire')->change();
		});

		// Transitional: The stage_id column may have already been added by the ADODB schema toolset
		if (!Capsule::schema()->hasColumn('email_templates_default', 'stage_id')) {
			Capsule::schema()->table('email_templates_default', function (Blueprint $table) {
				// pkp/pkp-lib#4796 stage ID as a filter parameter to email templates
				$table->bigInteger('stage_id')->nullable();
			});
		}

		// pkp/pkp-lib#6301: Indexes may be missing that affect search performance.
		// (These are added for 3.2.1-2 so may or may not be present for this upgrade code.)
		$schemaManager = Capsule::connection()->getDoctrineSchemaManager();
		if (!in_array('submissions_publication_id', $schemaManager->listTableIndexes('submissions'))) {
			Capsule::schema()->table('submissions', function (Blueprint $table) {
				$table->index(['submission_id'], 'submissions_publication_id');
			});
		}
		if (!in_array('submission_search_object_submission', $schemaManager->listTableIndexes('submission_search_objects'))) {
			Capsule::schema()->table('submission_search_objects', function (Blueprint $table) {
				$table->index(['submission_id'], 'submission_search_object_submission');
			});
		}

		// pkp/pkp-lib#6093 Don't allow nulls (previously an upgrade workaround)
		Capsule::schema()->table('user_settings', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->default(0)->change();
			$table->bigInteger('assoc_id')->default(0)->change();
		});
		Capsule::schema()->table('announcement_types', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
		});
		Capsule::schema()->table('email_templates', function (Blueprint $table) {
			$table->bigInteger('context_id')->default(0)->change();
		});
		Capsule::schema()->table('genres', function (Blueprint $table) {
			$table->bigInteger('seq')->change();
			$table->smallInteger('supplementary')->default(0)->change();
		});
		Capsule::schema()->table('event_log', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('email_log', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('notes', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('review_forms', function (Blueprint $table) {
			$table->bigInteger('assoc_type')->change();
			$table->bigInteger('assoc_id')->change();
		});
		Capsule::schema()->table('review_assignments', function (Blueprint $table) {
			$table->bigInteger('review_round_id')->change();
		});
		Capsule::schema()->table('authors', function (Blueprint $table) {
			$table->bigInteger('publication_id')->change();
		});
		Capsule::schema()->table('edit_decisions', function (Blueprint $table) {
			$table->bigInteger('review_round_id')->change();
		});
		Capsule::connection()->unprepared('UPDATE review_assignments SET review_form_id=NULL WHERE review_form_id=0');

		$this->_populateEmailTemplates();
		$this->_makeRemoteUrlLocalizable();

		// pkp/pkp-lib#6057: Migrate locale property from publications to submissions
		Capsule::schema()->table('submissions', function (Blueprint $table) {
			$table->string('locale', 14)->nullable();
		});
		$currentPublicationIds = Capsule::table('submissions')->pluck('current_publication_id');
		$submissionLocales = Capsule::table('publications')
			->whereIn('publication_id', $currentPublicationIds)
			->pluck('locale', 'submission_id');
		foreach ($submissionLocales as $submissionId => $locale) {
			Capsule::table('submissions as s')
				->where('s.submission_id', '=', $submissionId)
				->update(['locale' => $locale]);
		}
		Capsule::schema()->table('publications', function (Blueprint $table) {
			$table->dropColumn('locale');
		});

		// pkp/pkp-lib#6057 Submission files refactor
		$this->_migrateSubmissionFiles();
	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
		throw new PKP\install\DowngradeNotSupportedException();
	}

	/**
	 * @return void
	 * @brief populate email templates with new records for workflow stage id
	 */
	private function _populateEmailTemplates() {
		$xmlDao = new XMLDAO();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$data = $xmlDao->parseStruct($emailTemplateDao->getMainEmailTemplatesFilename(), array('email'));
		foreach ($data['email'] as $template) {
			$attr = $template['attributes'];
			if (array_key_exists('stage_id', $attr)) {
				Capsule::table('email_templates_default')->where('email_key', $attr['key'])->update(array('stage_id' => $attr['stage_id']));
			}
		}
	}

	/**
	 * @return void
	 * @brief make remoteUrl navigation item type multilingual and drop the url column
	 */
	private function _makeRemoteUrlLocalizable() {
		$contextService = Services::get('context');
		$contextIds = $contextService->getIds();
		foreach ($contextIds as $contextId) {
			$context = $contextService->get($contextId);
			$locales = $context->getData('supportedLocales');

			$navigationItems = Capsule::table('navigation_menu_items')->where('context_id', $contextId)->pluck('url', 'navigation_menu_item_id')->filter()->all();
			foreach ($navigationItems as $navigation_menu_item_id => $url) {
				foreach ($locales as $locale) {
					Capsule::table('navigation_menu_item_settings')->insert([
						'navigation_menu_item_id' => $navigation_menu_item_id,
						'locale' => $locale,
						'setting_name' => 'remoteUrl',
						'setting_value' => $url,
						'setting_type' => 'string'
					]);
				}
			}
		}

		$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
		$site = $siteDao->getSite();
		$supportedLocales = $site->getSupportedLocales();
		$navigationItems = Capsule::table('navigation_menu_items')->where('context_id', '0')->pluck('url', 'navigation_menu_item_id')->filter()->all();
		foreach ($navigationItems as $navigation_menu_item_id => $url) {
			foreach ($supportedLocales as $locale) {
				Capsule::table('navigation_menu_item_settings')->insert([
					'navigation_menu_item_id' => $navigation_menu_item_id,
					'locale' => $locale,
					'setting_name' => 'remoteUrl',
					'setting_value' => $url,
					'setting_type' => 'string'
				]);
			}
		}

		Capsule::schema()->table('navigation_menu_items', function (Blueprint $table) {
			$table->dropColumn('url');
		});
	}

	/**
	 * Migrate submission files after major refactor
	 *
	 *	- Add files table to manage underlying file storage
	 *	- Replace the use of file_id/revision as a unique id with a single
	 * 		auto-incrementing submission_file_id, and update all references.
	 *	- Move revisions to a submission_file_revisons table.
	 *	- Drop unused columns in submission_files table.
	 *
	 * @see pkp/pkp-lib#6057
	 */
	private function _migrateSubmissionFiles() {
		import('lib.pkp.classes.submission.SubmissionFile'); // SUBMISSION_FILE_ constants

		// Create a new table to track files in file storage
		Capsule::schema()->create('files', function (Blueprint $table) {
			$table->bigInteger('file_id')->autoIncrement();
			$table->string('path', 255);
		});

		// Create a new table to track submission file revisions
		Capsule::schema()->create('submission_file_revisions', function (Blueprint $table) {
			$table->bigInteger('revision_id')->autoIncrement();
			$table->bigInteger('submission_file_id');
			$table->bigInteger('file_id');
		});

		// Add columns to submission_files table
		Capsule::schema()->table('submission_files', function (Blueprint $table) {
			$table->bigInteger('new_file_id'); // Renamed at the end of the migration
		});

		// Drop unique keys that will cause trouble while we're migrating
		Capsule::schema()->table('review_round_files', function (Blueprint $table) {
			$table->dropUnique('review_round_files_pkey');
		});

		// Create entry in files and revisions tables for every submission_file
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$rows = Capsule::table('submission_files')
			->orderBy('file_id')
			->orderBy('revision')
			->get([
				'file_id',
				'revision',
				'submission_id',
				'genre_id',
				'file_stage',
				'date_uploaded',
				'original_file_name'
			]);
		foreach ($rows as $row) {
			// Reproduces the removed method SubmissionFile::_generateFileName()
			// genre is %s because it can be blank with review attachments
			$filename = sprintf(
				'%d-%s-%d-%d-%d-%s.%s',
				$row->submission_id,
				$row->genre_id,
				$row->file_id,
				$row->revision,
				$row->file_stage,
				date('Ymd', strtotime($row->date_uploaded)),
				strtolower_codesafe($fileManager->parseFileExtension($row->original_file_name))
			);
			$contextId = Capsule::table('submissions')->where('submission_id', '=', $row->submission_id)->first()->context_id;
			$path = sprintf(
				'%s/%s/%s',
				Services::get('submissionFile')->getSubmissionDir($contextId, $row->submission_id),
				$this->_fileStageToPath($row->file_stage),
				$filename
			);
			if (!Services::get('file')->fs->has($path)) {
				throw new Exception("A submission file was expected but not found at $path.");
			}
			$newFileId = Capsule::table('files')->insertGetId(['path' => $path]);
			Capsule::table('submission_files')
				->where('file_id', $row->file_id)
				->where('revision', $row->revision)
				->update(['new_file_id' => $newFileId]);
			Capsule::table('submission_file_revisions')->insert([
				'submission_file_id' => $row->file_id,
				'file_id' => $newFileId,
			]);

			// Update revision data in event logs
			$eventLogIds = Capsule::table('event_log_settings')
				->where('setting_name', '=', 'fileId')
				->where('setting_value', '=', $row->file_id)
				->pluck('log_id');
			Capsule::table('event_log_settings')
				->whereIn('log_id', $eventLogIds)
				->where('setting_name', 'fileRevision')
				->where('setting_value', '=', $row->revision)
				->update(['setting_value' => $newFileId]);
		}

		// Collect rows that will be deleted because they are old revisions
		// They are identified by the new_file_id column, which is the only unique
		// column on the table at this point.
		$newFileIdsToDelete = [];

		// Get all the unique file_ids. For each one, determine the latest revision
		// in order to keep it in the table. The others will be flagged for removal
		$revisionRowFileIds = Capsule::table('submission_files')
			->groupBy('file_id')
			->pluck('file_id');
		foreach ($revisionRowFileIds as $revisionRowFileId) {
			$submissionFileRows = Capsule::table('submission_files')
				->where('file_id', '=', $revisionRowFileId)
				->orderBy('revision', 'desc')
				->get([
					'file_id',
					'new_file_id',
				]);
			$latestFileId = $submissionFileRows[0]->new_file_id;
			foreach ($submissionFileRows as $submissionFileRow) {
				if ($submissionFileRow->new_file_id !== $latestFileId) {
					$newFileIdsToDelete[] = $submissionFileRow->new_file_id;
				}
			}
		}

		// Delete the rows for old revisions
		Capsule::table('submission_files')
			->whereIn('new_file_id', $newFileIdsToDelete)
			->delete();

		// Set assoc_type and assoc_id for all review round files
		// Run this before migration to internal review file stages
		$rows = Capsule::table('review_round_files')->get();
		foreach ($rows as $row) {
			Capsule::table('submission_files')
				->where('file_id', '=', $row->file_id)
				->whereIn('file_stage', [SUBMISSION_FILE_REVIEW_FILE, SUBMISSION_FILE_REVIEW_REVISION])
				->update([
					'assoc_type' => ASSOC_TYPE_REVIEW_ROUND,
					'assoc_id' => $row->review_round_id,
				]);
		}

		// Update name of event log params to reflect new file structure
		Capsule::table('event_log_settings')
			->where('setting_name', 'fileId')
			->update(['setting_name' => 'submissionFileId']);
		Capsule::table('event_log_settings')
			->where('setting_name', 'fileRevision')
			->update(['setting_name' => 'fileId']);

		// Restructure submission_files and submission_file_settings tables
		Capsule::schema()->table('submission_files', function (Blueprint $table) {
			$table->renameColumn('file_id', 'submission_file_id');
			$table->renameColumn('new_file_id', 'file_id');
			$table->renameColumn('source_file_id', 'source_submission_file_id');
			$table->renameColumn('date_uploaded', 'created_at');
			$table->renameColumn('date_modified', 'updated_at');
			$table->dropColumn('revision');
			$table->dropColumn('source_revision');
			$table->dropColumn('file_size');
			$table->dropColumn('file_type');
			$table->dropColumn('original_file_name');
			$table->foreign('file_id')->references('file_id')->on('files');
		});
		Capsule::schema()->table('submission_file_settings', function (Blueprint $table) {
			$table->renameColumn('file_id', 'submission_file_id');
			$table->string('setting_type', 6)->default('string')->change();
		});

		// Update columns in related tables
		Capsule::schema()->table('review_round_files', function (Blueprint $table) {
			$table->renameColumn('file_id', 'submission_file_id');
			$table->dropColumn('revision');
			$table->unique(['submission_id', 'review_round_id', 'submission_file_id'], 'review_round_files_pkey');
			$table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
		});
		Capsule::schema()->table('review_files', function (Blueprint $table) {
			$table->renameColumn('file_id', 'submission_file_id');
			$table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
		});
		Capsule::schema()->table('submission_file_revisions', function (Blueprint $table) {
			$table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
			$table->foreign('file_id')->references('file_id')->on('files');
		});
	}

	/**
	 * Get the directory of a file based on its file stage
	 *
	 * @param int $fileStage ONe of SUBMISSION_FILE_ constants
	 * @return string
	 */
	private function _fileStageToPath($fileStage) {
		import('lib.pkp.classes.submission.SubmissionFile');
		static $fileStagePathMap = [
			SUBMISSION_FILE_SUBMISSION => 'submission',
			SUBMISSION_FILE_NOTE => 'note',
			SUBMISSION_FILE_REVIEW_FILE => 'submission/review',
			SUBMISSION_FILE_REVIEW_ATTACHMENT => 'submission/review/attachment',
			SUBMISSION_FILE_REVIEW_REVISION => 'submission/review/revision',
			SUBMISSION_FILE_FINAL => 'submission/final',
			SUBMISSION_FILE_COPYEDIT => 'submission/copyedit',
			SUBMISSION_FILE_DEPENDENT => 'submission/proof',
			SUBMISSION_FILE_PROOF => 'submission/proof',
			SUBMISSION_FILE_PRODUCTION_READY => 'submission/productionReady',
			SUBMISSION_FILE_ATTACHMENT => 'attachment',
			SUBMISSION_FILE_QUERY => 'submission/query',
		];

		if (!isset($fileStagePathMap[$fileStage])) {
			throw new Exception('A file assigned to the file stage ' . $fileStage . ' could not be migrated.');
		}

		return $fileStagePathMap[$fileStage];
	}
}
