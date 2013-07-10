<div id="poststuff" class="metabox-holder m-settings">
    <form action='' method='post'>

        <input type='hidden' name='page' value='<?php echo $page; ?>' />
        <input type='hidden' name='action' value='updateoptions' />

        <?php
        wp_nonce_field('update-coursepress-options');
        ?>
        <div class="postbox">
            <h3 class="hndle" style='cursor:auto;'><span><?php _e('Slugs', 'cp'); ?></span></h3>
            <div class="inside">
                <p class='description'><?php _e('A slug is a few words that describe a post or a page. Slugs are usually a URL friendly version of the post title (which has been automatically generated by WordPress), but a slug can be anything you like. Slugs are meant to be used with <a href="options-permalink.php">permalinks</a> as they help describe what the content at the URL is. Post slug substitutes the <strong>"%posttitle%"</strong> placeholder in a custom permalink structure.', 'cp'); ?></p>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row"><?php _e('Course Slug', 'cp'); ?></th>
                            <td>
                                <?php
                                esc_html_e(trailingslashit(get_option('home')));
                                ?>&nbsp;<input type='text' name='course_slug' id='course_slug' value='<?php esc_attr_e($this->get_course_slug());
                                ?>' />&nbsp;/
                                
                                <p class='description'><?php _e('Your course URL will look like: ', 'cp'); ?><?php echo esc_html_e(trailingslashit(get_option('home')));?><?php echo esc_attr_e($this->get_course_slug());?><?php _e('/example-course-name/', 'cp');?></p>
                                
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php _e('Enrollment Process page', 'cp'); ?></th>
                            <td>
                                <?php
                                esc_html_e(trailingslashit(get_option('home')));
                                ?>&nbsp;<input type='text' name='enrollment_process_slug' id='enrollment_process_slug' value='<?php esc_attr_e($this->get_enrollment_process_slug());
                                ?>' />&nbsp;/
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php _e('Signup / Login page', 'cp'); ?></th>
                            <td>
                                <?php
                                esc_html_e(trailingslashit(get_option('home')));
                                ?>&nbsp;<input type='text' name='signup_slug' id='signup_slug' value='<?php esc_attr_e($this->get_signup_page_slug());
                                ?>' />&nbsp;/
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php _e('Student Dashboard page', 'cp'); ?></th>
                            <td>
                                <?php
                                esc_html_e(trailingslashit(get_option('home')));
                                ?>&nbsp;<input type='text' name='student_dashboard_slug' id='student_dashboard_slug' value='<?php esc_attr_e($this->get_student_dashboard_slug());
                                ?>' />&nbsp;/
                            </td>
                        </tr>
                        
                        
                        
                    </tbody>
                </table>
                
                

            </div>
        </div>

        <?php
        do_action('coursepress_general_options_page');
        ?>

        <p class="submit">
            <?php submit_button(__('Save Changes', 'cp')); ?>
        </p>

    </form>
</div>