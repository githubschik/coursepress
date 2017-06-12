<?php
/**
 * Class CoursePress_Course
 *
 * @since 3.0
 * @package CoursePress
 */
class CoursePress_Course extends CoursePress_Utility {
	/**
	 * CoursePress_Course constructor.
	 *
	 * @param int|WP_Post $course
	 */
	public function __construct( $course ) {
		if ( ! $course instanceof WP_Post ) {
			$course = get_post( (int) $course );
		}

		if ( ! $course instanceof WP_Post
		     || $course->post_type != 'course' ) {
			return $this->wp_error();
		}

		$this->setUp( array(
			'ID' => $course->ID,
			'post_title' => $course->post_title,
			'post_except' => $course->post_excerpt,
			'post_content' => $course->post_content,
			'post_status' => $course->post_status,
			'post_name' => $course->post_name,
		) );

		// Set course meta
		$this->setUpCourseMetas();
	}

	function wp_error() {
		return new WP_Error( 'wrong_param', __( 'Invalid course ID!', 'cp' ) );
	}

	function setUpCourseMetas() {
		$settings = $this->get_settings();
		$date_format = coursepress_get_option( 'date_format' );
		$time_now = current_time( 'timestamp' );
		$date_keys = array( 'course_start_date', 'course_end_date', 'enrollment_start_date', 'enrollment_end_date' );

		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, $date_keys ) && ! empty( $value ) ) {
				$timestamp = strtotime( $value, $time_now );
				$value = date_i18n( $date_format, $timestamp );

				// Add timestamp info
				$this->__set( $key . '_timestamp', $timestamp );
			}

			// Legacy fixes
			if ( 'enrollment_type' == $key && 'anyone' == $value )
				$value = 'registered';
			if ( 'on' == $value || 'yes' == $value )
				$value = true;
			if ( 'off' == $value || '' == $value )
				$value = false;

			$this->__set( $key, $value );
		}

		// Legacy: fix course_type meta
		if ( ! $this->__get( 'with_modules' ) )
			$this->__set( 'with_modules', true );
		if ( ! $this->__get( 'course_type' ) )
			$this->__set( 'course_type', 'auto-moderated' );
	}

	function get_settings() {
		$course_meta = array(
			'course_type' => 'auto-moderated',
			'course_language' => __( 'English', 'cp' ),
			'allow_discussion' => false,
			'allow_workbook' => false,
			'payment_paid_course' => false,
			'listing_image' => '',
			'listing_image_thumbnail_id' => 0,
			'featured_video' => '',
			'enrollment_type' => 'registered',
			'enrollment_passcode' => '',

			'course_view' => 'normal',
			'structure_level' => 'unit',
			'course_open_ended' => true,
			'course_start_date' => 0,
			'course_end_date' => '',
			'enrollment_open_ended' => false,
			'enrollment_start_date' => '',
			'enrollment_end_date' => '',
			'class_limited' => '',
			'class_size' => '',

			'pre_completion_title' => __( 'Almost there!', 'CP_TD' ),
			'pre_completion_content' => '',
			'minimum_grade_required' => 100,
			'course_completion_title' => __( 'Congratulations, You Passed!', 'CP_TD' ),
			'course_completion_content' => '',
			'course_failed_title' => __( 'Sorry, you did not pass this course!', 'CP_TD' ),
			'course_failed_content' => '',
			'basic_certificate_layout' => '',
			'basic_certificate' => false,
			'certificate_background' => '',
			'cert_margin' => array(
				'top' => 0,
				'left' => 0,
				'right' => 0,
			),
			'page_orientation' => 'L',
			'cert_text_color' => '#5a5a5a'
		);

		$id = $this->__get( 'ID' );
		$settings = get_post_meta( $id, 'course_settings', true );
		$settings = wp_parse_args( $settings, $course_meta );

		return $settings;
	}

	/**
	 * Returns course title.
	 *
	 * @return string
	 */
	function get_the_title() {
		return $this->__get( 'post_title' );
	}

	/**
	 * Returns course summary.
	 *
	 * @param int $length
	 *
	 * @return bool|null|string
	 */
	function get_summary( $length = 140 ) {
		$summary = $this->__get( 'post_excerpt' );
		$length++;

		if ( mb_strlen( $summary ) > $length ) {
			$sub = mb_substr( $summary, 0, $length - 5 );
			$words = explode( ' ', $sub );
			$cut = ( mb_strlen( $words[ count( $words ) - 1 ] ) );

			if ( $cut < 0 )
				return mb_substr( $sub, 0, $cut );
			else
				return $sub;
		}

		return $summary;
	}

	function get_feature_image_url() {
		return $this->__get( 'listing_image' );
	}

	/**
	 * Get the course feature image.
	 *
	 * @param int $width
	 * @param int $height
	 *
	 * @return null|string
	 */
	function get_feature_image( $width = 235, $height = 235 ) {
		$id = $this->__get( 'ID' );

		if ( ! $width )
			$width = coursepress_get_setting( 'course/image_width', 235 );
		if ( ! $height )
			$height = coursepress_get_setting( 'course/image_height', 235 );

		$listing_image = $this->get_feature_image_url();

		// Try post-thumbnail
		if ( ! $listing_image ) {
			if ( has_post_thumbnail( $id ) )
				$listing_image = get_the_post_thumbnail( $id, array( $width, $height ), array( 'class' => 'course-feature-image' ) );
		} else {
			$listing_image = $this->create_html(
				'img',
				array(
					'src' => esc_url( $listing_image ),
					'class' => 'course-listing-image',
					'width' => $width,
					'height' => $height,
				)
			);
		}

		return $listing_image;
	}

	function get_feature_video_url() {
		return $this->__get( 'featured_video' );
	}

	function get_feature_video( $width = 235, $height = 235 ) {
		$feature_video = $this->get_feature_video_url();

		if ( ! $width )
			$width = coursepress_get_setting( 'course/image_width', 235 );
		if ( ! $height )
			$height = coursepress_get_setting( 'course/image_height', 235 );

		if ( ! empty( $feature_video ) ) {
			$attr = array(
				'src' => esc_url_raw( $feature_video ),
				'class' => 'course-feature-video',
				'width' => $width,
				'height' => $height,
			);

			return $this->create_html( 'video', $attr );
		}

		return null;
	}

	function get_media( $width = 235, $height = 235 ) {
		$media_type = coursepress_get_setting( 'course/details_media_type', 'image' );
		$image = $this->get_feature_image( $width, $height );
		$video = $this->get_feature_video( $width, $height );


		if ( 'image' == $media_type )
			if ( ! empty( $image ) )
				return $image;
			else
				return $video;
		else
			if ( ! empty( $video ) )
				return $video;
			else
				return $image;
	}

	function get_description() {
		$description = $this->__get( 'post_content' );

		// @todo: Fix HTML formatting issue here

		return $description;
	}

	function get_course_start_date() {
		$open_ended = $this->__get( 'course_open_ended' );

		if ( $open_ended )
			return __( 'Anytime', 'cp' );
		else
			return $this->__get( 'course_start_date' );
	}

	function get_course_end_date() {
		return $this->__get( 'course_end_date' );
	}

	function get_course_dates( $separator = ' - ' ) {
		$open_ended = $this->__get( 'course_open_ended' );

		if ( $open_ended )
			return __( 'Anytime', 'cp' );

		return implode( $separator, array( $this->get_course_start_date(), $this->get_course_start_date() ) );
	}

	function get_enrollment_start_date() {
		$open_ended = $this->__get( 'enrollment_open_ended' );

		if ( $open_ended )
			return __( 'Anytime', 'cp' );

		return $this->__get( 'enrollment_start_date' );
	}

	function get_enrollment_end_date() {
		return $this->__get( 'enrollment_end_date' );
	}

	function get_enrollment_dates( $separator = ' - ' ) {
		$open_ended = $this->__get( 'enrollment_open_ended' );

		if ( $open_ended )
			return __( 'Anytime', 'cp' );

		return implode( $separator, array( $this->get_enrollment_start_date(), $this->get_enrollment_end_date() ) );
	}

	function get_course_language() {
		return $this->__get( 'course_language' );
	}

	function get_course_cost() {
		$price = __( 'FREE', 'cp' );

		if ( $this->__get( 'payment_paid_course' ) ) {
			$price = $this->__get( 'mp_product_price' );
			$is_on_sale = $this->__get( 'mp_sale_price_enabled' );

			if ( $is_on_sale ) {
				$sale_price = $this->__get( 'mp_product_sale_price' );

				$price = $sale_price . $this->create_html( 'em', array( 'class' => 'orig-price' ), $price );
			}

			// @todo: hook the price filter here
		}

		return $price;
	}

	function get_view_mode() {
		return $this->__get( 'course_view' );
	}

	function is_with_modules() {
		return $this->__get( 'with_modules' );
	}

	/**
	 * Check if the course has already started.
	 *
	 * @return bool
	 */
	function is_course_started() {
		$time_now = $this->date_time_now();
		$openEnded = $this->__get( 'course_open_ended' );
		$start_date = $this->__get( 'course_start_date_timestamp' );

		if ( empty( $openEnded )
		     && $start_date > 0
		     && $start_date > $time_now )
			return false;

		return true;
	}

	/**
	 * Check if the course is no longer open.
	 *
	 * @return bool
	 */
	function has_course_ended() {
		$time_now = $this->date_time_now();
		$openEnded = $this->__get( 'course_open_ended' );
		$end_date = $this->__get( 'course_end_date_timestamp' );

		if ( empty( $openEnded )
		     && $end_date > 0
		     && $end_date < $time_now ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the course is available
	 *
	 * @return bool
	 */
	function is_available() {
		$is_available = $this->is_course_started();

		if ( $is_available ) {
			// Check if the course hasn't ended yet
			if ( $this->has_course_ended() )
				$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Check if enrollment is open.
	 *
	 * @return bool
	 */
	function is_enrollment_started() {
		$time_now = $this->date_time_now();
		$enrollment_open = $this->__get( 'enrollment_open_ended' );
		$start_date = $this->__get( 'enrollment_start_date_timestamp' );

		if ( empty( $enrollment_open )
		     && $start_date > 0
		     && $start_date > $time_now )
			return false;

		return true;
	}

	/**
	 * Check if enrollment has closed.
	 *
	 * @return bool
	 */
	function has_enrollment_ended() {
		$time_now = $this->date_time_now();
		$enrollment_open = $this->__get( 'enrollment_open_ended' );
		$end_date = $this->__get( 'enrollment_end_date_timestamp' );

		if ( empty( $enrollment_open )
		     && $end_date > 0
		     && $end_date < $time_now )
			return true;

		return false;
	}

	/**
	 * Check if user can enroll to the course.
	 *
	 * @return bool
	 */
	function user_can_enroll() {
		$available = $this->is_available();

		if ( $available ) {
			// Check if enrollment has started
			$available = $this->is_enrollment_started();

			// Check if enrollment already ended
			if ( $available && $this->has_course_ended() )
				$available = false;
		}

		return $available;
	}

	private function _get_instructors() {
		$id = $this->__get( 'ID' );
		$instructor_ids = get_post_meta( $id, 'instructor' );
		$instructor_ids = array_filter( $instructor_ids );

		if ( ! empty( $instructor_ids ) )
			return $instructor_ids;

		// Legacy call
		// @todo: Delete this meta
		$instructor_ids = get_post_meta( $id, 'instructors', true );

		if ( ! empty( $instructor_ids ) )
			foreach ( $instructor_ids as $instructor_id )
				coursepress_add_course_instructor( $instructor_id, $id );

		return $instructor_ids;
	}

	/**
	 * Count total number of course instructors.
	 *
	 * @return int
	 */
	function count_instructors() {
		return count( $this->_get_instructors() );
	}

	/**
	 * Get course instructors.
	 *
	 * @return array An array of WP_User object on success.
	 */
	function get_instructors() {
		$instructors = array();
		$instructor_ids = $this->_get_instructors();

		if ( ! empty( $instructor_ids ) )
			foreach ( $instructor_ids as $instructor_id )
				$instructors[ $instructor_id ] = new CoursePress_Instructor( $instructor_id );

		return $instructors;
	}

	function get_instructors_link() {
		$instructors = $this->get_instructors();
		$links = array();

		if ( ! empty( $instructors ) ) {
			foreach ( $instructors as $instructor ) {
				$links[] = $this->create_html(
					'a',
					array(
						'href' => esc_url( $instructor->get_instructor_profile_link() ),
					),
					$instructor->get_name()
				);
			}
		}

		return $links;
	}

	private function _get_facilitators() {
		$id = $this->__get( 'ID' );
		$facilitator_ids = get_post_meta( $id, 'facilitator' );

		if ( is_array( $facilitator_ids ) && ! empty( $facilitator_ids ) )
			return array_unique( array_filter( $facilitator_ids ) );

		return array();
	}

	/**
	 * Count the total number of course facilitators.
	 *
	 * @return int
	 */
	function count_facilitators() {
		return count( $this->_get_facilitators() );
	}

	/**
	 * Get course facilitators.
	 *
	 * @return array of WP_User object
	 */
	function get_facilitators() {
		$facilitator_ids = $this->_get_facilitators();

		return array_map( 'get_userdata', $facilitator_ids );
	}

	private function _get_students() {
		$id = $this->__get( 'ID' );
		$student_ids = get_post_meta( $id, 'student' );

		if ( is_array( $student_ids ) && ! empty( $student_ids ) )
			return array_unique( array_filter( $student_ids ) );

		return array();
	}

	/**
	 * Count total number of students in a course.
	 *
	 * @return int
	 */
	function count_students() {
		return count( $this->_get_students() );
	}

	/**
	 * Get course students
	 *
	 * @return array of CoursePress_User object
	 */
	function get_students() {
		$students = array();
		$student_ids = $this->_get_students();

		if ( ! empty( $student_ids ) ) {
			foreach ( $student_ids as $student_id ) {
				$students[ $student_id ] = new CoursePress_User( $student_id );
			}
		}

		return $students;
	}

	function count_certified_students() {
		// @todo: count certified students here
		return 0;
	}

	/**
	 * Get an array of categories of the course.
	 *
	 * @return array
	 */
	function get_category() {
		$id = $this->__get( 'ID' );
		$course_category = wp_get_object_terms( $id, 'course_category' );
		$cats = array();

		if ( ! empty( $course_category ) )
			foreach ( $course_category as $term )
				$cats[ $term->term_id ] = $term->name;

		return $cats;
	}

	function get_permalink() {
		$course_name = $this->__get( 'post_name' );

		return coursepress_get_main_courses_url() . trailingslashit( $course_name );
	}

	function get_discussion_url() {
		$course_url = $this->get_permalink();
		$discussion_slug = coursepress_get_setting( 'slugs/discussions', 'discussions' );

		return $course_url . trailingslashit( $discussion_slug );
	}

	function get_grades_url() {
		$course_url = $this->get_permalink();
		$grades_slug = coursepress_get_setting( 'slugs/grades', 'grades' );

		return $course_url . trailingslashit( $grades_slug );
	}

	function get_workbook_url() {
		$course_url = $this->get_permalink();
		$workbook_slug = coursepress_get_setting( 'slugs/workbook', 'workbook' );

		return $course_url . trailingslashit( $workbook_slug );
	}

	private function _get_units( $published = true, $ids = true ) {
		$args = array(
			'post_type'      => 'unit',
			'post_status'    => $published ? 'publish' : 'any',
			'post_parent'    => $this->__get( 'ID' ),
			'posts_per_page' => - 1, // Units are often retrieve all at once
			'suppress_filters' => true,
			'meta_key' => 'unit_order',
			'orderby' => 'meta_value_num',
			'order' => 'ASC',
		);

		if ( $ids )
			$args['fields'] = 'ids';

		$units = get_posts( $args );

		return $units;
	}

	function count_units( $published = true ) {
		$units = $this->_get_units( $published );

		return count( $units );
	}

	function get_units( $published = true ) {
		$units = array();
		$results = $this->_get_units( $published, false );

		if ( ! empty( $results ) ) {
			$previousUnit = false;

			foreach ( $results as $unit ) {
				$unitClass = new CoursePress_Unit( $unit, $this );
				$unitClass->__set( 'previousUnit', $previousUnit );
				$previousUnit = $unitClass;
				$units[] = $unitClass;
			}
		}

		return $units;
	}

	function get_course_structure( $show_details = false ) {
		/**
		 * @var $user CoursePress_Student
		 */

		$course_id = $this->__get( 'ID' );
		$user = coursepress_get_user();
		$has_access = $user->has_access_at( $course_id );
		$structure = '';
		$units = $this->get_units( ! $has_access );

		if ( $units ) {
			foreach ( $units as $unit ) {
				$unit_structure = $unit->get_unit_structure( false, $show_details );
				$structure .= $this->create_html( 'li', false, $unit_structure );
			}
			$structure = $this->create_html( 'ul', array( 'class' => 'tree unit-tree' ), $structure );
		}

		return $structure;
	}
}