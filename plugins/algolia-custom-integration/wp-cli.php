<?php

if (!(defined('WP_CLI') && WP_CLI)) {
	return;
}

class Algolia_Command {

	public function reindex_post($args, $assoc_args) {
		global $algolia;
		global $table_prefix;

		$type = isset($assoc_args['type']) ? $assoc_args['type'] : 'post';

		$aiswpdemo = $table_prefix.$type;
		$index = $algolia->initIndex(
			apply_filters('algolia_index_name', $aiswpdemo, $type)
		);
		$index->clearObjects()->wait();

		$paged = 1;
		$count = 0;

		do {
			$posts = new WP_Query([
				'posts_per_page' => 100,
				'paged' => $paged,
				'post_type' => $type,
				'post_status' => 'publish',
			]);

			if (!$posts->have_posts()) {
				break;
			}

			$records = [];

			foreach ($posts->posts as $post) {
				if (!empty($assoc_args['verbose'])) {
					WP_CLI::line('Indexing ['.$post->post_title.']');
				}
				$record = (array) apply_filters($type.'_to_record', $post);

				if (! isset($record['objectID'])) {
					$record['objectID'] = implode('#', [$post->post_type, $post->ID]);
				}

				$records[] = $record;
				$count++;
			}

			$index->saveObjects($records);

			$paged++;

		} while (true);

		WP_CLI::success("$count $type entries indexed in Algolia");
	}

	public function copy_config($args, $assoc_args) {
		global $algolia;

		$srcIndexName = $assoc_args['from'];
		$destIndexName = $assoc_args['to'];

		if (!$srcIndexName || !$destIndexName) {
			throw new InvalidArgumentException('--from and --to arguments are required');
		}

		$scope = [];

		if (isset($assoc_args['settings']) && $assoc_args['settings']) {
			$scope[] = 'settings';
		}
		if (isset($assoc_args['synonyms']) && $assoc_args['synonyms']) {
			$scope[] = 'synonyms';
		}
		if (isset($assoc_args['rules']) && $assoc_args['rules']) {
			$scope[] = 'rules';
		}

		if (!empty($scope)) {
			$algolia->copyIndex($srcIndexName, $destIndexName, ['scope' => $scope]);
			WP_CLI::success('Copied '.implode(', ', $scope)." from $srcIndexName to $destIndexName");
		} else {
			WP_CLI::warning('Nothing to copy, use --settings, --synonyms or --rules.');
		}
	}

	public function set_config($args, $assoc_args) {
		global $algolia;

		$canonicalIndexName = $assoc_args['index'];
		if (!$canonicalIndexName) {
			throw new InvalidArgumentException('--index argument is required');
		}

		$forward = isset($assoc_args['forward-to-replicas']) ? $assoc_args['forward-to-replicas'] : false;

		$index = $algolia->initIndex(
			apply_filters('algolia_index_name', $canonicalIndexName)
		);

		$replicas = [];

		if ($assoc_args['settings']) {
			$settings = (array) apply_filters('get_'.$canonicalIndexName.'_settings', []);

			if (isset($settings['replicas'])) {
				$replicas = $settings['replicas'];

				$settings['replicas'] = array_map(function ($replicaName) {
					return apply_filters('algolia_index_name', $replicaName);
				}, $settings['replicas']);
			}

			if ($settings) {
				$index->setSettings($settings, ['forwardToReplicas' => $forward]);
			}

			WP_CLI::success('Push settings to '.$index->getIndexName());
		}

		if ($assoc_args['synonyms']) {
			$synonyms = (array) apply_filters('get_'.$canonicalIndexName.'_synonyms', []);
			if ($synonyms) {
				$index->replaceAllSynonyms($synonyms, ['forwardToReplicas' => $forward]);
			}

			WP_CLI::success('Push synonyms to '.$index->getIndexName());
		}

		if ($assoc_args['rules']) {
			$rules = (array) apply_filters('get_'.$canonicalIndexName.'$rules', []);
			if ($rules) {
				$index->replaceAllRules($rules, ['forwardToReplicas' => $forward]);
			}

			WP_CLI::success('Push rules to '.$index->getIndexName());
		}

		foreach ($replicas as $replicaName) {
			$this->set_config([], ['index' => $replicaName] + $assoc_args);
		}
	}


}


WP_CLI::add_command('algolia', 'Algolia_Command');
