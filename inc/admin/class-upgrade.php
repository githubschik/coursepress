<?php
/**
 * Class CoursePress_Upgrade
 *
 * @since 3.0
 * @package CoursePress
 */
class CoursePress_Admin_Upgrade  extends CoursePress_Admin_Page {

	protected $cp;
	private $status;
	private $count = 0;
	private $courses = array();

	public function __construct( CoursePress $cp ) {
		$this->status = get_option( 'coursepress_upgrade', 'no upgrade required' );
		$this->cp = $cp;
		if ( 'need to be upgraded' !== $this->status ) {
			return;
		}
		/**
		 * always try to upgrade settings, independly of courses
		 */
		add_action( 'admin_init', array( $this, 'upgrade_settings' ) );
		/**
		 * try to upgrade courses
		 */
		$this->count_courses();
		if ( 0 === $this->count ) {
			return;
		}
		add_action( 'admin_notices', array( $this, 'upgrade_is_needed_notice' ) );
		add_filter( 'coursepress_admin_menu_screens', array( $this, 'add_admin_submenu' ), 11 );
		add_filter( 'coursepress_admin_localize_array', array( $this, 'i18n' ) );
	}

	/**
	 * upgrade CoursePress Settings recursive helper
	 *
	 * @since 3.0.0
	 */
	private function set_true_false( $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$settings[ $key ] = $this->set_true_false( $value );
			} elseif ( is_string( $value ) ) {
				switch ( $value ) {
					case 'on':
						$settings[ $key ] = true;
					break;
					case 'off':
						$settings[ $key ] = false;
					break;
				}
			}
		}
		return $settings;
	}

	/**
	 * upgrade CoursePress Settings
	 *
	 * @since 3.0.0
	 */
	public function upgrade_settings() {
		global $CoursePress, $wpdb;
		$version = get_option( 'coursepress_settings_version' );
		if ( empty( $version ) ) {
			$settings = coursepress_get_setting();
			$settings = $this->set_true_false( $settings );
			$settings['general']['version'] = $CoursePress->version;
			update_option( 'coursepress_settings_version', $CoursePress->version );
			coursepress_update_setting( true, $settings );
			/**
			 * upgrade notifications
			 */
			$wpdb->update(
				$wpdb->posts,
				array( 'post_type' => 'cp_notification' ),
				array( 'post_type' => 'notifications' )
			);
			$args = array(
				'nopaging' => true,
				'post_type' => 'cp_notification',
				'fields' => 'ids',
			);
			$query = new WP_Query( $args );
			if ( isset( $query->posts ) && ! empty( $query->posts ) ) {
				foreach ( $query->posts as $id ) {
					$wpdb->update(
						$wpdb->postmeta,
						array( 'meta_key' => 'alert_course' ),
						array(
							'meta_key' => 'course_id',
							'post_id' => $id,
						)
					);
				}
			}
		}
	}

	/**
	 * Add i18n to JavaScript _coursepress object.
	 *
	 * @since 3.0.0
	 */
	public function i18n( $data ) {
		$data['text']['upgrade'] = array(
			'status' => array(
				'in_progress' => __( 'Upgrading in progress, please wait.', 'cp' ),
				'upgraded' => __( 'Upgraded.', 'cp' ),
			),
		);
		return $data;
	}

	/**
	 * Add admin submenu to upgrade courses.
	 *
	 * @since 3.0.0
	 */
	public function add_admin_submenu( $screens ) {
		$menu = $this->add_submenu(
			__( 'Upgrade courses', 'cp' ),
			'coursepress_create_course_cap',
			'coursepress_upgrade',
			'get_upgrade_page'
		);
		array_unshift( $screens, $menu );
		return $screens;
	}

	public function process_page() {
	}

	private function set_courses() {
		if ( empty( $this->courses ) ) {
			$this->courses = coursepress_get_accessible_courses( false );
		}
	}

	public function get_upgrade_page() {
		$this->set_courses();
		$courses_to_upgrade = array();
		foreach ( $this->courses as $course ) {
			if ( 0 < version_compare( 3, $course->coursepress_version ) ) {
				$courses_to_upgrade[] = $course;
			}
		}
		$args = array(
			'count' => $this->count,
			'courses' => $courses_to_upgrade,
			'nonce' => wp_create_nonce( __CLASS__ ),
		);
		coursepress_render( 'views/admin/upgrade', $args );
		coursepress_render( 'views/tpl/common' );
	}

	public function count_courses() {
		$this->set_courses();
		$this->count = 0;
		foreach ( $this->courses as $course ) {
			if ( 0 < version_compare( 3, $course->coursepress_version ) ) {
				$this->count++;
			}
		}
		if ( 0 === $this->count ) {
			update_option( 'coursepress_upgrade', 'no upgrade required' );
		}
	}

	public function upgrade_is_needed_notice() {
		if ( 1 > $this->count ) {
			return;
		}
		$screen_id = get_current_screen()->id;
		if ( preg_match( '/page_coursepress_upgrade$/', $screen_id ) ) {
			return;
		}

		$class = 'notice notice-error';
		$message = esc_html( sprintf( _n( 'You have %d course to update.', 'You have %d courses to update.', $this->count, 'cp' ), $this->count ) );
		$message .= PHP_EOL.PHP_EOL;
		$message .= sprintf(
			'<a href="%s">%s</a>',
			esc_url( add_query_arg( 'page', 'coursepress_upgrade', admin_url( 'admin.php' ) ) ),
			esc_html__( 'Go to CoursePress Upgrade page.', 'cp' )
		);
		printf( '<div class="%s">', esc_attr( $class ) );
		printf( '<h2>%s</h2>', esc_html__( 'CoursePress Upgrade', 'cp' ) );
		echo wpautop( $message );
		echo '</div>';
	}

	/**
	 * Upgrade course one by one.
	 *
	 * @since 3.0.0
	 */
	public function upgrade_course_by_id( $course_id ) {
		global $CoursePress;
		/**
		 * check course
		 */
		$course = new CoursePress_Course( $course_id );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$meta = get_post_meta( $course_id );
		$result = array(
			'students' => array(
				'total' => 0,
				'added' => 0,
			),
			'course_id' => $course_id,
			'message' => __( 'Course was upgraded successfully.', 'cp' ),
		);
		/**
		 * Instructors
		 */
		$users = get_post_meta( $course_id, 'cp_instructors', true );
		if ( ! empty( $users )  ) {
			foreach ( $users as $user_id ) {
				coursepress_add_course_instructor( $user_id, $course_id );
			}
			delete_post_meta( $course_id, 'cp_instructors' );
		}
		/**
		 * Facilitators
		 */
		$users = get_post_meta( $course_id, 'course_facilitator', false );
		if ( ! empty( $users )  ) {
			foreach ( $users as $user_id ) {
				coursepress_add_course_facilitator( $user_id, $course_id );
			}
			delete_post_meta( $course_id, 'course_facilitator' );
		}
		/**
		 * get course with modules
		 */
		$course_units = $course->get_units( false );
		$units = array();
		$data = array_keys( $course->structure_visible_pages );
		foreach ( $course_units as $unit ) {
			$units[ $unit->ID ] = $unit->get_steps( false, true );
		}
		/**
		 * upgrade units
		 */
		foreach ( $course_units as $unit ) {
			$post_content = html_entity_decode( $unit->post_content );
			$args = array(
				'ID' => $unit->ID,
				'post_content' => $post_content,
				'meta_input' => array(),
			);
			if ( ! empty( $post_content ) ) {
				$args['meta_input']['use_description'] = true;
			}
			if ( isset( $unit->unit_feature_image ) && ! empty( $unit->unit_feature_image ) ) {
				$args['meta_input']['use_feature_image'] = true;
			}
			$page_description = get_post_meta( $unit->ID, 'page_description', true );
			if ( ! empty( $page_description ) && is_array( $page_description ) ) {
				foreach ( $page_description as $page_description_key => $page_description_value ) {
					$page_description[ $page_description_key ] = html_entity_decode( $page_description_value );
				}
				$args['meta_input']['page_description'] = $page_description;
			}
			wp_update_post( $args );
		}
		/**
		 * upgrade steps
		 */
		$types = array(
			'input-select' => 'select',
			'input-radio' => 'single',
			'input-quiz' => 'multiple',
		);
		foreach ( $units as $unit_id => $steps ) {
			foreach ( $steps as $step_id => $step ) {
				$args = array(
					'ID' => $step_id,
					'meta_input' => array(),
				);
				if ( isset( $step->post_content ) && ! empty( $step->post_content ) ) {
					$args['meta_input']['show_content'] = true;
				}
				$type = get_post_meta( $step_id, 'module_type', true );
				$answers = $checked = array();
				switch ( $type ) {
					case 'input-select':
					case 'input-radio':
					case 'input-quiz':
						$answers = get_post_meta( $step_id, 'answers', true );
						$checked = array();
						$answer = get_post_meta( $step_id, 'answers_selected', true );
						foreach ( $answers as $id => $a ) {
							if ( is_array( $answer ) ) {
								$checked[ $id ] = in_array( $id, $answer )? 1:'';
							} else {
								$checked[ $id ] = $id == $answer? 1:'';
							}
						}
						$args['meta_input']['module_type'] = 'input-quiz';
						$args['meta_input']['questions'] = array(
						'view'.$step_id => array(
							'title' => $step->post_title,
							'question' => $step->post_content,
							'order' => 0,
							'type' => $types[ $type ],
							'options' => array(
								'answers' => $answers,
								'checked' => $checked,
							),
						),
						);
					break;
				}
				wp_update_post( $args );
			}
		}
		/**
		 * course_enrolled_student_id
		 */
		$students = get_post_meta( $course_id, 'course_enrolled_student_id', false );
		if ( ! empty( $students ) && is_array( $students ) ) {
			$result['students']['total'] = count( $students );
			foreach ( $students as $student_id ) {
				$student = new CoursePress_User( $student_id );
				$user = new WP_User( $student_id );
				$user->add_role( 'coursepress_student' );
				if ( $student->add_course_student( $course, false ) ) {
					$result['students']['added']++;
					$meta_name = sprintf( 'course_%d_progress', $course_id );
					$progress = get_user_meta( $student_id, $meta_name, true );
					if ( isset( $progress['completion'] ) ) {
						foreach ( $progress['completion'] as $unit_id => $data ) {
							$completed = isset( $data['completed'] ) && coursepress_is_true( $data['completed'] );
							/**
							 * upgrade step progress
							 */
							if ( isset( $progress['completion'][ $unit_id ]['answered'] ) ) {
								foreach ( $progress['completion'][ $unit_id ]['answered'] as $step_id => $value ) {
									$answered = coursepress_is_true( $value );
									if ( $answered ) {
										$value = array(
											'progress' => 100,
										);
										$progress = coursepress_set_array_val( $progress, 'completion/' . $unit_id . '/steps/'.$step_id, $value );
									}
								}
							}
							/**
							 * upgrade passed
							 */
							$passed = coursepress_get_array_val( $progress, 'completion/'.$unit_id.'/passed' );
							if ( ! empty( $passed ) && is_array( $passed ) ) {
								$value = array();
								foreach ( $passed as $step_id => $p ) {
									if ( $p ) {
										$value[] = $step_id;
									}
								}
								$progress = coursepress_set_array_val( $progress, 'completion/' . $unit_id . '/passed', $value );
							}
							/**
							 * upgrade responses
							 */
							$responses = coursepress_get_array_val( $progress, 'units/'.$unit_id.'/responses' );
							if ( ! empty( $responses ) && is_array( $responses ) ) {
								foreach ( $responses as $step_id => $step_response ) {
									/**
									 * TODO: check where is last answer
									 */
									$response = array_shift( $step_response );
									if ( isset( $response['response'] ) ) {
										$progress = coursepress_set_array_val( $progress, 'units/' . $unit_id . '/responses/'.$step_id, $response );
										$fixed_response = coursepress_get_array_val( $progress, 'units/' . $unit_id . '/responses/'.$step_id.'/response' );
										/**
										 * TODO: multi quiz recalculate value
										 */
										$progress = coursepress_set_array_val( $progress, 'units/' . $unit_id . '/responses/'.$step_id.'/response', array( $fixed_response ) );
									}
									/**
									 * TODO: check where is last grade
									 */
									if ( isset( $response['grades'] ) ) {
										$value = array_shift( $response['grades'] );
										foreach ( array( 'graded_by', 'grade', 'date' ) as $key ) {
											if ( isset( $value[ $key ] ) ) {
												$progress = coursepress_set_array_val( $progress, 'units/' . $unit_id . '/responses/'.$step_id.'/'.$key, $value[ $key ] );
											}
										}
									}
									/**
									 * fix writable answer
									 */
									if ( isset( $units[ $unit_id ][ $step_id ] ) && 'written' === $units[ $unit_id ][ $step_id ]->type ) {
										$progress_key = 'units/'.$unit_id.'/responses/'.$step_id.'/response';
										$value = coursepress_get_array_val( $progress, $progress_key );
										$value = array(
											$course_id => array(
												$unit_id => array(
													$step_id => $value,
												),
											),
										);
										$progress = coursepress_set_array_val( $progress, $progress_key, $value );
									}
								}
							}
						}
					}
					if ( ! empty( $progress ) ) {
						$progress = coursepress_set_array_val( $progress, 'version_last', coursepress_get_array_val( $progress, 'version' ) );
						$progress = coursepress_set_array_val( $progress, 'version', $CoursePress->version );
						$student->add_student_progress( $course_id, $progress );
					}
				}
			}
		}
		/**
		 * Visibility
		 */
		$visible_keys = array(
			'units',
			'pages',
			'modules',
		);
		foreach ( $visible_keys as $key ) {
			$key = 'cp_structure_visible_'.$key;
			$visible[ $key ] = array();
			if (
				isset( $meta[ $key ] )
				&& is_array( $meta[ $key ] )
				&& ! empty( $meta[ $key ] )
			) {
				$visible[ $key ] = maybe_unserialize( $meta[ $key ][0] );
			}
		}
		/**
		 * updagre forums
		 */
		$args = array(
			'post_type' => 'discussions',
			'post_status' => 'any',
			'fields' => 'ids',
		);
		$query = new WP_Query( $args );
		foreach ( $query->posts as $id ) {
			$course_id = get_post_meta( $id, 'course_id', true );
			if ( ! empty( $course_id ) ) {
				$args = array(
					'ID' => $id,
					'post_parent' => $course_id,
				);
				wp_update_post( $args );
			}
		}
		/**
		 * update course CoursePress version
		 */
		$value = add_post_meta( $course->ID, 'coursepress_version', $CoursePress->version, true );
		if ( false == $value ) {
			update_post_meta( $course->ID, 'coursepress_version', $CoursePress->version );
		}
		return $result;
	}
}
