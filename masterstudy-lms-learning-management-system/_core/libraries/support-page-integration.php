<?php
add_action(
	'admin_init',
	function() {
		/**
		 * Add Support Page
		 */
		STM_Support_Page::set_api_urls(
			'masterstudy-lms-learning-management-system',
			array(
				'promo' => 'https://promo-dashboard.stylemixthemes.com/wp-content/dashboard-promo/masterstudy-lms-learning-management-system_posts.json',
			)
		);

		STM_Support_Page::set_data(
			'masterstudy-lms-learning-management-system',
			array(
				'header'     => array(
					array(
						'title' => __( 'MasterStudy Help Center', 'masterstudy-lms-learning-management-system' ),
					),
				),
				'help_items' => array(
					'documentation' => array(
						'buttons' => array(
							array(
								'href' => 'https://docs.stylemixthemes.com/masterstudy-lms/',
							),
						),
					),
					'ticket'        => array(
						'has-pro'      => \STM_LMS_Helpers::is_pro(),
						'has-pro-plus' => \STM_LMS_Helpers::is_pro_plus(),
					),
					'video'         => array(
						'buttons' => array(
							array(
								'href' => 'https://www.youtube.com/playlist?list=PL3Pyh_1kFGGDW2EmMYkKrALDAYzIjafUd',
							),
						),
					),
					'requests'      => array(
						'buttons' => array(
							array(
								'href' => 'https://stylemixthemes.cnflx.io/boards/masterstudy-lms',
							),
						),
					),
					'community'     => array(
						'buttons' => array(
							array(
								'href' => 'https://www.facebook.com/groups/masterstudylms',
							),
						),
					),
					'features'      => array(
						'title'        => __( 'Get MasterStudy and Enjoy PRO Features', 'masterstudy-lms-learning-management-system' ),
						'title_pro'    => __( 'Get MasterStudy and Enjoy PRO PLUS Features', 'masterstudy-lms-learning-management-system' ),
						'description'  => __( 'Upgrade now and access a world of pro features, advanced addons, and limitless possibilities for your journey. Turbocharge your courses and make your e-learning platform truly outstanding!', 'masterstudy-lms-learning-management-system' ),
						'buttons'      => array(
							array(
								'href' => 'https://stylemixthemes.com/wordpress-lms-plugin/pricing/',
							),
							array(
								'href' => 'https://stylemixthemes.com/wordpress-lms-plugin/',
							),
						),
						'has-pro'      => \STM_LMS_Helpers::is_pro(),
						'has-pro-plus' => \STM_LMS_Helpers::is_pro_plus(),
					),
				),
				'review'     => array(
					'review_form' => array(
						'has_review' => get_option( 'stm_lms_feedback_added', false ),
						'buttons'    => array(
							array(
								'href' => 'https://bit.ly/33D44gQ',
							),
						),
					),
				),
				'news'       => array(
					'blog_list' => array(
						'category_id' => '394',
						'buttons'     => array(
							array(
								'href' => 'https://stylemixthemes.com/wp/category/masterstudy/',
							),
						),
					),
				),
			)
		);
	}
);
