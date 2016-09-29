<?php
/**
 * Front-End template file.
 *
 * @package CoursePress
 */

/**
 * Template data to display a single course unit in front-end.
 */
class CoursePress_Template_Unit {
	/**
	 * Render the complete contents of a single course unit.
	 *
	 * This function is called by:
	 * CoursePress_View_Front_Course::render_course_unit()
	 *
	 * It renders the contents of a VirtualPage.
	 *
	 * @since  2.0.0
	 * @return string HTML Content of the page.
	 */
	public static function unit_with_modules( $course_id = 0, $unit_id = 0, $page = 0, $student_id = 0 ) {
		if ( empty( $course_id ) ) {
			$course = CoursePress_Helper_Utility::the_course();
			$course_id = $course->ID;
		} else {
			$course = get_post( $course_id );
		}

		if ( empty( $unit_id ) ) {
			$unit = CoursePress_Helper_Utility::the_post();
			$unit_id = $unit->ID;
		} else {
			$unit = get_post( $unit_id );
		}

		if ( empty( $page ) ) {
			$page = (int) CoursePress_Helper_Utility::the_pagination();
		}
		if ( empty( $student_id ) ) {
			$student_id = get_current_user_id();
		}

		$is_instructor = false;
		$student_progress = 0;
		$enrolled = false;
		$can_preview_page = true;
		$show_page_title = false;
		$page_title = '';
		$preview_pages = array();
		$next_page = false;
		$next_unit = false;

		if ( $student_id ) {
			$student_progress = CoursePress_Data_Student::get_completion_data( $student_id, $course_id );
			$instructors = CoursePress_Data_Course::get_instructors( $course_id );
			$is_instructor = in_array( $student_id, $instructors );
			$enrolled = CoursePress_Data_Course::student_enrolled( $student_id, $course_id );
		}

		$preview = CoursePress_Data_Course::previewability( $course_id );
		$can_update_course = CoursePress_Data_Capabilities::can_update_course( $course_id );
		$course_url = CoursePress_Data_Course::get_course_url( $course_id );

		if ( ! isset( $preview['has_previews'] ) ) {
			$can_preview_page = false;
		} elseif ( ! isset( $preview['structure'][ $unit->ID ] ) ) {
			$can_preview_page = false;
		} elseif ( ! isset( $preview['structure'][ $unit->ID ][ $page ] ) ) {
			$can_preview_page = false;
		} elseif ( empty( $preview['structure'][ $unit->ID ][ $page ] ) ) {
			$can_preview_page = false;
		} elseif ( isset( $preview['structure'][ $unit->ID ] ) ) {
			$can_preview_page = $preview['structure'][ $unit->ID ];
		}

		$can_preview_page = ! $can_preview_page ? $can_update_course : $can_preview_page;

		if ( ! $enrolled && ! $can_preview_page && ! $is_instructor ) {
			return __( 'Sorry. You are not permitted to view this part of the course.', 'cp' );
		}

		$view_mode = CoursePress_Data_Course::get_setting( $course_id, 'course_view', 'normal' );

		// Let BackboneJS take over if its in Focus mode.
		if ( 'focus' == $view_mode ) {
			global $wp;

			$item_id = $page;
			$type = 'section';

			if ( ! empty( $wp->query_vars['module_id'] ) ) {
				$type = 'module';
				$item_id = $wp->query_vars['module_id'];
			} elseif ( $_POST && ! empty( $_POST['module_id'] ) ) {
				// Check for $_POST submission
				$type = 'module';
				$item_id = (int) $_POST['module_id'];
			}

			$format = '<div class="coursepress-focus-view">[coursepress_focus_item course="%s" unit="%s" type="%s" item_id="%s"]</div>';
			$shortcode = sprintf( $format, $course_id, $unit_id, $type, $item_id );

			return do_shortcode( $shortcode );
		}

		$page_titles = get_post_meta( $unit->ID, 'page_title', true );
		$show_page_titles = get_post_meta( $unit->ID, 'show_page_title', true );
		$total_pages = count( $page_titles );

		// Can't exceed total pages, so do the last one.
		$page = min( $total_pages, $page );

		/**
		 * Filter the visibility of submenu.
		 *
		 * @since 2.0
		 **/
		$show_submenu = apply_filters( 'coursepress_show_submenu', true );

		if ( true === $show_submenu ) {
			$content = do_shortcode( '[course_unit_submenu]' );
		}

		// Check if available
		$previous_unit_id = CoursePress_Data_Unit::get_previous_unit_id( $course_id, $unit_id );
		$availability = CoursePress_Data_Unit::get_unit_availability_status( $course_id, $unit_id, $previous_unit_id );

		if ( empty( $availability['available'] ) ) {
			$message = CoursePress_Data_Shortcode_Unit::module_status(
				array(
					'course_id' => $course_id,
					'unit_id' => $unit_id,
					'previous_unit' => $previous_unit_id,
				)
			);
			$content .= sprintf( '<p><em>%s</em></p>', $message );

			if ( $previous_unit_id ) {
				$unit_url = CoursePress_Data_Unit::get_unit_url( $previous_unit_id );
				$format = '<span class="next-button unit unit-%s"><a href="%s"><button>%s</button></a></span> ';
				$content .= sprintf( $format, $previous_unit_id, esc_url_raw( $unit_url ), __( 'Previous Unit', 'cp' ) );
			}

			return $content;
		}

		// Get modules for the current page only.
		$modules = CoursePress_Data_Course::get_unit_modules(
			$unit->ID,
			array( 'publish' ),
			false,
			false,
			array( 'page' => $page )
		);

		$before_html = apply_filters( 'coursepress_before_unit_modules', '' );

		$content .= sprintf( '<div class="cp-error">%s</div>', $before_html );
		$content .= '<div class="cp unit-wrapper unit-' . $unit->ID . ' course-' . $course_id . '">';

		// Page Title.
		if ( isset( $show_page_titles[ $page - 1 ] ) ) {
			$show_page_title = cp_is_true( $show_page_titles[ $page - 1 ] );
		}

		if ( $show_page_title && isset( $page_titles[ 'page_' . $page ] ) ) {
			$page_title = CoursePress_Helper_Utility::filter_content(
				$page_titles[ 'page_' . $page ]
			);

			if ( $page_title ) {
				$content .= '<div class="unit-page-header unit-section-header">';

				$page_feature_image = get_post_meta( $unit->ID, 'page_feature_image', true );
				if ( ! empty( $page_feature_image[ 'page_'. $page ] ) ) {
					$feature_image = sprintf( '<img src="%s" alt="%s" />', esc_url( $page_feature_image[ 'page_'. $page ] ), esc_attr( basename( $page_feature_image[ 'page_'. $page ] ) ) );
					$content .= '<div class="unit-page-feature-image section-thumbnail">' . $feature_image . '</div>';
				}

				$content .= '<h3 class="page-title unit-section-title">' . $page_title . '</h3>';

				$page_description = get_post_meta( $unit->ID, 'page_description', true );
				if ( ! empty( $page_description[ 'page_' . $page ] ) ) {
					$content .= $page_description[ 'page_' . $page ];
				}

				$content .= '</div>';
			}
		}

		// Modules.
		$module_template = wp_nonce_field( 'coursepress_submit_modules', '_wpnonce', true, false );
		$module_template .= sprintf( '<input type="hidden" name="course_id" value="%s" />', $course_id );
		$module_template .= sprintf( '<input type="hidden" name="unit_id" value="%s" />', $unit_id );
		$module_template .= sprintf( '<input type="hidden" name="student_id" value="%s" />', get_current_user_id() );

		foreach ( $modules as $module ) {
			$preview_modules = array();
			$can_preview_module = false;
			$attributes = CoursePress_Data_Module::attributes( $module );
			$attributes['course_id'] = $course_id;

			$method = 'render_' . str_replace( '-', '_', $attributes['module_type'] );
			$template = 'CoursePress_Template_Module';

			if ( ! empty( $preview['structure'] ) && ! empty( $preview['structure'][ $unit->ID ] ) && isset( $preview['structure'][ $unit->ID ][ $page ] ) ) {
				$preview_modules = array_keys( $preview['structure'][ $unit->ID ][ $page ] );
			}
			if ( in_array( $module->ID, $preview_modules ) || $can_update_course ) {
				$can_preview_module = true;
			} elseif ( isset( $preview['structure'][ $unit->ID ] ) ) {
				$can_preview_module = ! is_array( $preview['structure'][ $unit->ID ] );
			} else {
				$can_preview_module = false;
			}

			if ( ! $enrolled && ! $can_preview_module && ! $is_instructor ) {
				continue;
			}

			if ( $enrolled || $is_instructor || $can_update_course || 'output' == $attributes['mode'] ) {
				$module_template .= CoursePress_Template_Module::template( $module->ID );
				// Modules seen here!
			}
		}

		// Pager.
		$preview_pages = array();
		if ( isset( $preview['structure'][ $unit->ID ] ) ) {
			$preview_pages = array_keys( $preview['structure'][ $unit->ID ] );
		}

		$url_path = CoursePress_Data_Unit::get_unit_url( $unit->ID );
		$url_path .= trailingslashit( 'page' );
		$has_submit_button = false;

		$unit_pager = '<div class="pager unit-pager">';

		// Show pager only if there's more than 1 pages.
		if ( $total_pages > 1 ) {
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$unit_url = $url_path . $i;

				if ( $enrolled || $can_update_course || ( ! empty( $preview_pages ) && in_array( $i, $preview_pages ) ) ) {

					// Disable anchor if it is the current page
					if ( $page == $i ) {
						$format = '<span class="page page-%1$s">%1$s</span>';
					} else {
						$format = '<input type="submit" name="next_page" value="%1$s" class="page page-%1$s" />';
					}
					$unit_pager .= sprintf( $format, $i );
				}
			}

			// Next Page.
			if ( false === $enrolled && false === $can_update_course ) {
				for ( $i = $page + 1; $i <= $total_pages; $i++ ) {
					if ( in_array( $i, $preview_pages ) ) {
						$next_page = $i;
					} elseif ( ! is_array( $preview['structure'][ $unit->ID ] ) ) {
						$next_page = $i;
					}

					if ( $next_page ) {
						break;
					}
				}
			} else {
				$next_page = $page + 1;

				if ( $next_page > $total_pages ) {
					$next_page = false;
				}
			}

			if ( (int) $next_page > 0 ) {
				$unit_url = $url_path . $next_page;
				$has_submit_button = true;
				$format = '<input type="submit" name="next_page" value="%1$s" class="next-button page page-%1$s" />';
				$unit_pager .= sprintf( $format, $next_page );
			}
		}

		// Next unit.
		$units = CoursePress_Data_Course::get_unit_ids( $course_id );
		$unit_index = array_search( $unit->ID, $units );

		// If current user can update the current course, redo!
		if ( ! $unit_index && $can_update_course ) {
			$units = CoursePress_Data_Course::get_unit_ids( $course_id, 'any' );
			$unit_index = array_search( $unit->ID, $units );
		}

		for ( $i = $unit_index; $i < count( $units ); $i++ ) {
			$preview_units = array();

			if ( $unit_index == $i ) { continue; }
			if ( $unit_index >= count( $units ) - 1 ) { continue; }

			if ( isset( $preview['structure'] ) ) {
				$preview_units = array_keys( $preview['structure'] );
			}

			$can_preview_unit = in_array( $units[ $i ], $preview_units );
			if ( $is_instructor || $enrolled || $can_update_course ) {
				$unit_available = true;
			} elseif ( $can_preview_unit ) {
				$unit_available = CoursePress_Data_Unit::is_unit_available(
					$course_id,
					$units[ $i ],
					$units[ $unit_index ]
				);
			} else {
				$unit_available = false;
			}

			if ( $unit_available ) {
				$next_unit = $units[ $i ];
			}

			if ( $next_unit ) {
				break;
			}
		}

		if ( 0 < (int) $previous_unit_id ) {
			$unit_url = CoursePress_Data_Unit::get_unit_url( $previous_unit_id );
			$page_titles = get_post_meta( $previous_unit_id, 'page_title', true );

			if ( 1 < count( $page_titles ) ) {
				$unit_url .= trailingslashit( 'page' ) . count( $page_titles );
			}
			$format = '<a href="%s" class="button prev-button unit unit-%s">%s</a>';

			$unit_pager .= sprintf( $format, esc_url( $unit_url ), $previous_unit_id, __( 'Previous Unit', 'cp' ) );
		}

		if ( ! empty( $next_unit ) && empty( $next_page ) ) {
			$unit_url = CoursePress_Data_Unit::get_unit_url( $next_unit );
			$format = '<button type="submit" name="next_unit" value="%1$s" class="next-button unit unit-%1$s">%2$s</button>';
			$unit_pager .= sprintf( $format, $next_unit, __( 'Next Unit', 'cp' ) );
		}

		if ( false === $has_submit_button ) {
			$unit_pager .= sprintf( '<button type="submit" name="finish" class="next-button">%s</button>', __( 'Submit', 'cp' ) );
		}

		$unit_pager .= '</div>'; // .pager
		$format = '<form method="post" enctype="multipart/form-data" class="cp-form">%s</form>';
		$content .= sprintf( $format, $module_template . $unit_pager );
		$content .= '</div>'; // .unit-wrapper

		// Student Tracking:
		if ( $enrolled ) {
			CoursePress_Data_Student::visited_page(
				$student_id,
				$course_id,
				$unit->ID,
				$page,
				$student_progress
			);
		}

		return $content;
	}

	/**
	 * Render the unit archive of a single course.
	 *
	 * This function is called by:
	 * CoursePress_View_Front_Course::render_course_unit_archive()
	 *
	 * It renders the contents of a VirtualPage.
	 *
	 * @since  2.0.0
	 * @return string HTML Content of the page.
	 */
	public static function unit_archive() {
		$content = '';
		$content .= do_shortcode( '[course_unit_submenu]' );

		$content .= '
			<div class="instructors-content">
				' . do_shortcode( '[course_instructors style="list-flat" link="true"]' ) . '
			</div>
		';

		$content .= do_shortcode( '[unit_archive_list description="true"]' );

		return $content;
	}

}
