<?php

namespace MasterStudy\Lms\Utility;

use MasterStudy\Lms\Enums\QuestionType;
use MasterStudy\Lms\Plugin\PostType;

final class Question {
	public static function filter_allow_access( int $user_id, array $question_ids ): array {
		if ( empty( $question_ids ) ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => PostType::QUESTION,
				'post__in'       => $question_ids,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'author'         => user_can( $user_id, 'administrator' ) ? null : $user_id,
			)
		);

		return array_intersect( $question_ids, $post_ids );
	}

	public static function filter_matching_user_answers( array $data, string $separator, bool $image = false ): array {
		return array_map(
			function ( $user_answer ) use ( $data, $image ) {
				foreach ( $data['answers'] as $answer ) {
					$compare = $image && isset( $answer['text_image']['url'] )
						? "{$answer['text']}|{$answer['text_image']['url']}"
						: $answer['text'];
					if ( $user_answer === $compare ) {
						return $answer;
					}
				}

				return $user_answer;
			},
			explode(
				'[stm_lms_sep]',
				str_replace( "[$separator]", '', $data['last_answers']['user_answer'] ?? array() )
			)
		);
	}

	public static function sort_answers_by_order( array $answers, string $order, string $type ): array {
		$order      = wp_unslash( $order );
		$order_json = json_decode( $order );

		if ( empty( $order_json ) ) {
			$order = array_map( 'trim', explode( ',', $order ) );

			// If len of order more than actual len of answers then it means it is broken, so return original array
			if ( count( $order ) > count( $answers ) ) {
				return $answers;
			}
		} else {
			$order = $order_json;
		}

		$sorted = array();
		foreach ( $order as $txt ) {
			foreach ( $answers as $index => $row ) {
				$sort_values = array_map( 'strval', self::get_sort_values_by_type( $type, $row ) );

				if ( in_array( (string) $txt, $sort_values, true ) ) {
					$sorted[] = $row;
					unset( $answers[ $index ] );
					break;
				}
			}
		}

		return array_merge( $sorted, array_values( $answers ) );
	}

	public static function get_sort_value_by_type( string $type, array $arr ) {
		switch ( $type ) {
			case QuestionType::ITEM_MATCH:
				return $arr['question'] ?? '';
			case QuestionType::IMAGE_MATCH:
				return $arr['question_image']['id'] ?? '';
			default:
				$text  = $arr['text'] ?? '';
				$value = trim( rawurldecode( $text ) );

				if ( ! empty( $arr['text_image']['url'] ) ) {
					$value = trim( rawurldecode( "{$text}|{$arr['text_image']['url']}" ) );
				}

				return $value;
		}
	}

	private static function get_sort_values_by_type( string $type, array $arr ): array {
		$values = array( self::get_sort_value_by_type( $type, $arr ) );

		if ( QuestionType::ITEM_MATCH === $type || QuestionType::IMAGE_MATCH === $type ) {
			return $values;
		}

		$text = $arr['text'] ?? '';

		if ( '' !== $text ) {
			$values[] = trim( rawurldecode( $text ) );
			$values[] = $text;
		}

		return array_values( array_unique( $values, SORT_REGULAR ) );
	}

	public static function get_sorted_answers_ids( string $type, $answers ): string {
		if ( ! is_array( $answers ) ) {
			return '';
		}
		if ( in_array( $type, array( QuestionType::FILL_THE_GAP, QuestionType::KEYWORDS, QuestionType::QUESTION_BANK, QuestionType::SORTABLE ), true ) ) {
			return '';
		}

		$values = array_map(
			function ( $answer ) use ( $type ) {
				return is_array( $answer ) ? self::get_sort_value_by_type( $type, $answer ) : '';
			},
			$answers
		);

		return wp_json_encode(
			$values,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
		);
	}

	public static function get_last_sortable_answers( array $data ): array {
		return array_map(
			function ( $item ) {
				return array( 'text' => rawurldecode( $item ) );
			},
			explode( '[stm_lms_sep]', str_replace( '[stm_lms_sortable]', '', $data['last_answers']['user_answer'] ) )
		);
	}
}
