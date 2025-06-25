<?php

namespace MasterStudy\Lms\Repositories;

use MasterStudy\Lms\Enums\LessonType;
use MasterStudy\Lms\Plugin\PostType;
use MasterStudy\Lms\Utility\Traits\VideoTrait;
use RuntimeException;

final class LessonRepository extends AbstractRepository {

	use VideoTrait;

	protected static string $post_type = PostType::LESSON;

	protected static array $fields_post_map = array(
		'title'   => 'post_title',
		'content' => 'post_content',
	);

	protected static array $fields_meta_map = array(
		'type'                    => 'type',
		'duration'                => 'duration',
		'preview'                 => 'preview',
		'excerpt'                 => 'lesson_excerpt',
		'video_type'              => 'video_type',
		'audio_type'              => 'audio_type',
		'audio_required_progress' => 'audio_required_progress',
		'video_captions_ids'      => 'video_captions_ids',
	);

	protected static array $casts = array(
		'preview'                 => 'bool',
		'start_date'              => 'int',
		'presto_player_idx'       => 'int',
		'audio_required_progress' => 'int',
		'video_required_progress' => 'int',
	);

	public function create( array $data ): int {
		$this->set_video_captions_ids( $data );
		$post       = $this->post_data( $data );
		$post['ID'] = 0;

		$post_id = wp_insert_post( $post, true );

		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}

		if ( $post_id ) {
			$this->update_meta( $post_id, $data );
			$files_merged = array_merge( $data['files'] ?? array(), $data['video_captions'] ?? array() );
			$this->get_file_repository()->save_files( $files_merged ?? array(), $post_id, self::$post_type );
		}

		// needed for compatibility with old addons' code
		$data = array_merge(
			$data,
			$this->map_data( self::$fields_meta_map, $data )
		);

		do_action( 'masterstudy_lms_save_lesson', $post_id, $data );

		return $post_id;
	}

	public function get( $post_id ): ?array {
		$post = $this->get_post( $post_id );

		if ( null === $post ) {
			return null;
		}

		return $this->hydrate( $post );
	}

	public function save( array $data ): void {
		$this->set_video_captions_ids( $data );
		$post = $this->post_data( $data );

		wp_update_post( $post );

		$this->update_meta( $post['ID'], $data );
		$files_merged = array_merge( $data['files'] ?? array(), $data['video_captions'] ?? array() );
		$this->get_file_repository()->save_files( $files_merged ?? array(), $post['ID'], self::$post_type );

		// needed for compatibility with old addons' code
		$data = array_merge(
			$data,
			$this->map_data( self::$fields_meta_map, $data )
		);

		do_action( 'masterstudy_lms_save_lesson', $post['ID'], $data );
	}

	private function post_data( array $data ): array {
		return array(
			'ID'           => $data['id'] ?? 0,
			'post_title'   => $data['title'],
			'post_content' => apply_filters( 'masterstudy_lms_map_api_data', $data['content'] ?? '', 'post_content' ),
			'post_type'    => self::$post_type,
			'post_status'  => 'publish',
		);
	}

	protected function update_meta( $post_id, $data ): void {
		$map = $this->get_fields_mapping();

		if ( LessonType::VIDEO === $data['type'] || 'audio' === $data['type'] ) {
			$map += $this->get_video_fields_mapping( LessonType::VIDEO === $data['type'] ? $data['video_type'] ?? '' : $data['audio_type'] ?? '' );
		}

		foreach ( $map as $field => $meta_key ) {
			if ( array_key_exists( $field, $data ) ) {
				update_post_meta( $post_id, $meta_key, $this->convert_to_meta( $field, $data[ $field ] ) );
			}
		}
	}

	protected function cast( $field, $value ) {
		$cast = self::$casts[ $field ] ?? '';

		if ( 'int' === $cast ) {
			return ! empty( $value ) ? (int) $value : null;
		}

		return parent::cast( $field, $value );
	}

	private function hydrate( \WP_Post $post ) {
		$meta               = get_post_meta( $post->ID );
		$repository_files   = $this->get_file_repository()->get_files( $meta['lesson_files'][0] ?? null, true );
		$files              = array();
		$video_captions     = array();
		$video_captions_ids = ! empty( $meta['video_captions_ids'] ) ? maybe_unserialize( $meta['video_captions_ids'][0] ) : array();
		foreach ( $repository_files as $file ) {
			if ( in_array( $file['id'], $video_captions_ids, true ) ) {
				$video_captions[] = $file;
			} else {
				$files[] = $file;
			}
		}

		$lesson = array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'content'        => $post->post_content,
			'files'          => $files,
			'video_captions' => $video_captions,
		);

		foreach ( $this->get_fields_mapping() as $prop => $meta_key ) {
			$lesson[ $prop ] = $this->cast( $prop, $meta[ $meta_key ][0] ?? null );
		}

		if ( empty( $lesson['type'] ) ) {
			$lesson['type'] = 'text';
		}

		$lesson = $this->hydrate_video( $lesson, $meta, $post->post_type );

		return apply_filters( 'masterstudy_lms_lesson_hydrate', $lesson, $meta );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_fields_mapping(): array {
		return apply_filters( 'masterstudy_lms_lesson_fields_meta_mapping', self::$fields_meta_map );
	}

	private function get_file_repository(): FileMaterialRepository {
		return new FileMaterialRepository();
	}

	/**
	 * @param $data array
	 *
	 * @return void
	 */
	private function set_video_captions_ids( &$data ) {
		$data['video_captions_ids'] = array();
		if ( ! empty( $data['video_captions'] ) ) {
			foreach ( $data['video_captions'] as $file ) {
				$data['video_captions_ids'][] = $file['id'];
			}
		}
	}
}
