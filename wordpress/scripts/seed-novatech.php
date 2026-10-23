<?php
/**
 * Idempotent seed script for NovaTech Solutions (Stage 1 — WordPress + ACF Free + CF7).
 *
 * Executed via WP-CLI:
 *   wp eval-file /var/www/html/wp-content/scripts/seed-novatech.php
 *
 * Responsibilities:
 * 1. Builds and imports/updates the "NovaTech Corporate Landing Fields" ACF group
 *    (with show_in_rest = 1) and syncs it to themes/ai-hub-theme/acf-json/.
 * 2. Programmatically generates crisp PNG visuals via PHP GD in the Media Library
 *    (Hero architecture diagram, 5 Case Study visuals, 4 Testimonial avatars)
 *    without duplicating attachments on repeated runs.
 * 3. Creates or updates the Contact Form 7 form ("NovaTech Project Inquiry")
 *    with English validation/status messages and local Mailpit recipient.
 * 4. Creates or updates the "NovaTech Solutions" page (/novatech-solutions/),
 *    assigns page-novatech.php, and populates all ACF fields with B2B copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "This script must be run within WordPress (via WP-CLI).\n" );
}

/**
 * Helper to create a minimal ACF field definition array.
 */
function nt_make_field( $key, $name, $label, $type, $extra = array() ) {
	$defaults = array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'aria-label'        => '',
		'type'              => $type,
		'instructions'      => '',
		'required'          => 0,
		'conditional_logic' => 0,
		'wrapper'           => array(
			'width' => '',
			'class' => '',
			'id'    => '',
		),
		'default_value'     => '',
	);
	return array_merge( $defaults, $extra );
}

/**
 * Build the deterministic ACF Free Field Group definition.
 */
function nt_get_acf_field_group_definition() {
	// 1. Header Navigation sub-groups (nav_1 .. nav_5)
	$nav_subfields = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$nav_subfields[] = nt_make_field(
			"field_nt_nav_item_{$i}",
			"nav_{$i}",
			"Navigation Item #{$i}",
			'group',
			array(
				'layout'     => 'table',
				'sub_fields' => array(
					nt_make_field( "field_nt_nav_{$i}_enabled", 'enabled', 'Enabled', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
					nt_make_field( "field_nt_nav_{$i}_label", 'label', 'Label', 'text' ),
					nt_make_field( "field_nt_nav_{$i}_url", 'url', 'Anchor / URL', 'text' ),
				),
			)
		);
	}

	// 2. Hero Stats sub-groups (stat_1 .. stat_3)
	$hero_stat_subfields = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$hero_stat_subfields[] = nt_make_field(
			"field_nt_hero_stat_{$i}",
			"stat_{$i}",
			"Key Metric #{$i}",
			'group',
			array(
				'layout'     => 'table',
				'sub_fields' => array(
					nt_make_field( "field_nt_hero_stat_{$i}_val", 'value', 'Metric Value', 'text' ),
					nt_make_field( "field_nt_hero_stat_{$i}_lbl", 'label', 'Metric Label', 'text' ),
					nt_make_field( "field_nt_hero_stat_{$i}_dtl", 'detail', 'Context Note', 'text' ),
				),
			)
		);
	}

	// 3. Services Cards sub-groups (card_1 .. card_6)
	$service_card_subfields = array();
	for ( $i = 1; $i <= 6; $i++ ) {
		$service_card_subfields[] = nt_make_field(
			"field_nt_service_card_{$i}",
			"card_{$i}",
			"Service Card #{$i}",
			'group',
			array(
				'layout'     => 'block',
				'sub_fields' => array(
					nt_make_field( "field_nt_svc_{$i}_enabled", 'enabled', 'Enabled', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
					nt_make_field(
						"field_nt_svc_{$i}_icon",
						'icon_key',
						'Icon Key',
						'select',
						array(
							'choices'       => array(
								'cloud'         => 'Cloud Infrastructure & Kubernetes',
								'data'          => 'Data Streaming & Warehousing',
								'platform'      => 'Internal Developer Platforms',
								'security'      => 'Zero-Trust Security & Compliance',
								'ai'            => 'Applied ML & Retrieval Systems',
								'modernization' => 'Core System Modernization',
							),
							'default_value' => 'cloud',
							'return_format' => 'value',
						)
					),
					nt_make_field( "field_nt_svc_{$i}_badge", 'badge', 'Domain Badge', 'text' ),
					nt_make_field( "field_nt_svc_{$i}_title", 'title', 'Service Title', 'text' ),
					nt_make_field( "field_nt_svc_{$i}_desc", 'description', 'Service Description', 'textarea', array( 'rows' => 3 ) ),
					nt_make_field( "field_nt_svc_{$i}_deliv", 'deliverable_highlight', 'Key Deliverable', 'text' ),
					nt_make_field( "field_nt_svc_{$i}_ltxt", 'link_text', 'Link Label', 'text' ),
					nt_make_field( "field_nt_svc_{$i}_lurl", 'link_url', 'Link Target URL', 'text' ),
				),
			)
		);
	}

	// 4. Case Studies Slider sub-groups (slide_1 .. slide_5)
	$case_slide_subfields = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$case_slide_subfields[] = nt_make_field(
			"field_nt_case_slide_{$i}",
			"slide_{$i}",
			"Case Study Slide #{$i}",
			'group',
			array(
				'layout'     => 'block',
				'sub_fields' => array(
					nt_make_field( "field_nt_case_{$i}_enabled", 'enabled', 'Enabled', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
					nt_make_field(
						"field_nt_case_{$i}_cat",
						'category',
						'Industry Vertical',
						'select',
						array(
							'choices'       => array(
								'FinTech'             => 'FinTech & Payments',
								'Supply Chain'        => 'Global Supply Chain',
								'HealthTech'          => 'Clinical HealthTech',
								'Energy Grid'         => 'Smart Energy Grid',
								'SaaS Infrastructure' => 'B2B SaaS Infrastructure',
							),
							'default_value' => 'FinTech',
							'return_format' => 'value',
						)
					),
					nt_make_field( "field_nt_case_{$i}_client", 'client_name', 'Client Identifier (Demo)', 'text' ),
					nt_make_field( "field_nt_case_{$i}_title", 'title', 'Case Study Title', 'text' ),
					nt_make_field( "field_nt_case_{$i}_summary", 'summary', 'Engineering Summary', 'textarea', array( 'rows' => 3 ) ),
					nt_make_field( "field_nt_case_{$i}_mval", 'metric_value', 'Primary Metric Value', 'text' ),
					nt_make_field( "field_nt_case_{$i}_mlbl", 'metric_label', 'Primary Metric Label', 'text' ),
					nt_make_field( "field_nt_case_{$i}_smet", 'secondary_metric', 'Secondary Impact Metric', 'text' ),
					nt_make_field( "field_nt_case_{$i}_stack", 'tech_stack', 'Technology Stack', 'text' ),
					nt_make_field( "field_nt_case_{$i}_img", 'image', 'Case Visual', 'image', array( 'return_format' => 'array', 'preview_size' => 'medium' ) ),
					nt_make_field( "field_nt_case_{$i}_url", 'case_url', 'Action Link', 'text' ),
				),
			)
		);
	}

	// 5. Testimonials Slider sub-groups (slide_1 .. slide_4)
	$testimonial_slide_subfields = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$testimonial_slide_subfields[] = nt_make_field(
			"field_nt_testim_slide_{$i}",
			"slide_{$i}",
			"Testimonial Slide #{$i}",
			'group',
			array(
				'layout'     => 'block',
				'sub_fields' => array(
					nt_make_field( "field_nt_testim_{$i}_enabled", 'enabled', 'Enabled', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
					nt_make_field( "field_nt_testim_{$i}_quote", 'quote', 'Client Quote', 'textarea', array( 'rows' => 3 ) ),
					nt_make_field( "field_nt_testim_{$i}_name", 'author_name', 'Author Name', 'text' ),
					nt_make_field( "field_nt_testim_{$i}_role", 'author_role', 'Author Role', 'text' ),
					nt_make_field( "field_nt_testim_{$i}_comp", 'company', 'Company Name (Demo)', 'text' ),
					nt_make_field( "field_nt_testim_{$i}_scope", 'engagement_scope', 'Project Scope Tag', 'text' ),
					nt_make_field( "field_nt_testim_{$i}_rating", 'rating', 'Rating (1-5)', 'number', array( 'min' => 1, 'max' => 5, 'default_value' => 5 ) ),
					nt_make_field( "field_nt_testim_{$i}_avatar", 'avatar', 'Author Avatar', 'image', array( 'return_format' => 'array', 'preview_size' => 'thumbnail' ) ),
				),
			)
		);
	}

	// 6. Process Accordion sub-groups (step_1 .. step_4)
	$process_step_subfields = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$process_step_subfields[] = nt_make_field(
			"field_nt_proc_step_{$i}",
			"step_{$i}",
			"Delivery Phase #{$i}",
			'group',
			array(
				'layout'     => 'block',
				'sub_fields' => array(
					nt_make_field( "field_nt_proc_{$i}_enabled", 'enabled', 'Enabled', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
					nt_make_field( "field_nt_proc_{$i}_num", 'step_number', 'Phase Number', 'text' ),
					nt_make_field( "field_nt_proc_{$i}_title", 'title', 'Phase Title', 'text' ),
					nt_make_field( "field_nt_proc_{$i}_dur", 'duration_badge', 'Typical Duration', 'text' ),
					nt_make_field( "field_nt_proc_{$i}_sum", 'summary', 'Phase Description', 'textarea', array( 'rows' => 3 ) ),
					nt_make_field( "field_nt_proc_{$i}_deliv", 'deliverables', 'Key Deliverables (One per line)', 'textarea', array( 'rows' => 4 ) ),
					nt_make_field( "field_nt_proc_{$i}_gov", 'governance_note', 'Quality & Security Gate', 'text' ),
					nt_make_field( "field_nt_proc_{$i}_open", 'default_open', 'Expanded by Default', 'true_false', array( 'default_value' => ( $i <= 2 ? 1 : 0 ), 'ui' => 1 ) ),
				),
			)
		);
	}

	// 7. FAQ Accordion sub-groups (faq_1 .. faq_5)
	$faq_item_subfields = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$faq_item_subfields[] = nt_make_field(
			"field_nt_faq_item_{$i}",
			"faq_{$i}",
			"FAQ Item #{$i}",
			'group',
			array(
				'layout'     => 'block',
				'sub_fields' => array(
					nt_make_field( "field_nt_faq_{$i}_enabled", 'enabled', 'Enabled', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
					nt_make_field( "field_nt_faq_{$i}_cat", 'category_badge', 'Topic Badge', 'text' ),
					nt_make_field( "field_nt_faq_{$i}_q", 'question', 'Question', 'text' ),
					nt_make_field( "field_nt_faq_{$i}_a", 'answer', 'Answer', 'textarea', array( 'rows' => 4 ) ),
					nt_make_field( "field_nt_faq_{$i}_open", 'default_open', 'Expanded by Default', 'true_false', array( 'default_value' => ( 1 === $i ? 1 : 0 ), 'ui' => 1 ) ),
				),
			)
		);
	}

	return array(
		'key'                   => 'group_novatech_landing',
		'title'                 => 'NovaTech Corporate Landing Fields',
		'fields'                => array(
			// TAB 1: HEADER & BRANDING
			nt_make_field( 'field_nt_tab_header', '', 'Header & Branding', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_header_settings',
				'header_settings',
				'Header & Navigation Settings',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_hdr_logo', 'logo_text', 'Brand Name', 'text' ),
						nt_make_field( 'field_nt_hdr_badge', 'logo_badge', 'Brand Suffix / Badge', 'text' ),
						nt_make_field( 'field_nt_hdr_ann', 'announcement_text', 'Top Status Bar Text', 'text' ),
						nt_make_field( 'field_nt_hdr_cta_lbl', 'cta_label', 'Header CTA Label', 'text' ),
						nt_make_field( 'field_nt_hdr_cta_url', 'cta_url', 'Header CTA Target URL', 'text' ),
						nt_make_field( 'field_nt_hdr_nav', 'nav_items', 'Primary Navigation Links', 'group', array( 'layout' => 'block', 'sub_fields' => $nav_subfields ) ),
					),
				)
			),

			// TAB 2: HERO SECTION
			nt_make_field( 'field_nt_tab_hero', '', 'Hero Section', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_hero_section',
				'hero_section',
				'Hero Section Content',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_hero_eyebrow', 'eyebrow_badge', 'Eyebrow Badge', 'text' ),
						nt_make_field( 'field_nt_hero_title', 'title', 'Primary Headline (H1)', 'text' ),
						nt_make_field( 'field_nt_hero_sub', 'subtitle', 'Lead Paragraph', 'textarea', array( 'rows' => 3 ) ),
						nt_make_field( 'field_nt_hero_cta1_txt', 'primary_cta_text', 'Primary CTA Label', 'text' ),
						nt_make_field( 'field_nt_hero_cta1_url', 'primary_cta_url', 'Primary CTA URL', 'text' ),
						nt_make_field( 'field_nt_hero_cta2_txt', 'secondary_cta_text', 'Secondary CTA Label', 'text' ),
						nt_make_field( 'field_nt_hero_cta2_url', 'secondary_cta_url', 'Secondary CTA URL', 'text' ),
						nt_make_field( 'field_nt_hero_img', 'hero_image', 'Architecture Visual', 'image', array( 'return_format' => 'array', 'preview_size' => 'medium' ) ),
						nt_make_field( 'field_nt_hero_cap', 'architecture_caption', 'Visual Caption', 'text' ),
						nt_make_field( 'field_nt_hero_stats', 'stats', 'Verified Engineering Metrics', 'group', array( 'layout' => 'block', 'sub_fields' => $hero_stat_subfields ) ),
					),
				)
			),

			// TAB 3: SERVICES GRID
			nt_make_field( 'field_nt_tab_services', '', 'Services Grid', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_services_section',
				'services_section',
				'Core Engineering Services',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_svc_tag', 'section_tag', 'Section Eyebrow Tag', 'text' ),
						nt_make_field( 'field_nt_svc_title', 'section_title', 'Section Heading (H2)', 'text' ),
						nt_make_field( 'field_nt_svc_sub', 'section_subtitle', 'Section Intro', 'textarea', array( 'rows' => 2 ) ),
						nt_make_field( 'field_nt_svc_cards', 'cards', 'Service Cards (6 Fixed Slots in ACF Free)', 'group', array( 'layout' => 'block', 'sub_fields' => $service_card_subfields ) ),
					),
				)
			),

			// TAB 4: CASES SLIDER (SWIPER #1)
			nt_make_field( 'field_nt_tab_cases', '', 'Cases Slider (Swiper #1)', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_cases_section',
				'cases_slider_section',
				'Case Studies Carousel',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_cases_tag', 'section_tag', 'Section Eyebrow Tag', 'text' ),
						nt_make_field( 'field_nt_cases_title', 'section_title', 'Section Heading (H2)', 'text' ),
						nt_make_field( 'field_nt_cases_sub', 'section_subtitle', 'Section Intro', 'textarea', array( 'rows' => 2 ) ),
						nt_make_field( 'field_nt_cases_disc', 'demo_disclaimer', 'Demo Case Notice', 'text' ),
						nt_make_field( 'field_nt_cases_slides', 'slides', 'Case Study Slides (5 Fixed Slots)', 'group', array( 'layout' => 'block', 'sub_fields' => $case_slide_subfields ) ),
					),
				)
			),

			// TAB 5: TESTIMONIALS SLIDER (SWIPER #2)
			nt_make_field( 'field_nt_tab_testimonials', '', 'Testimonials Slider (Swiper #2)', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_testimonials_section',
				'testimonials_section',
				'Client Testimonials Carousel',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_testim_tag', 'section_tag', 'Section Eyebrow Tag', 'text' ),
						nt_make_field( 'field_nt_testim_title', 'section_title', 'Section Heading (H2)', 'text' ),
						nt_make_field( 'field_nt_testim_sub', 'section_subtitle', 'Section Intro', 'textarea', array( 'rows' => 2 ) ),
						nt_make_field( 'field_nt_testim_delay', 'autoplay_delay', 'Autoplay Delay (ms)', 'number', array( 'default_value' => 6000 ) ),
						nt_make_field( 'field_nt_testim_slides', 'slides', 'Testimonial Slides (4 Fixed Slots)', 'group', array( 'layout' => 'block', 'sub_fields' => $testimonial_slide_subfields ) ),
					),
				)
			),

			// TAB 6: PROCESS ACCORDION
			nt_make_field( 'field_nt_tab_process', '', 'Process Accordion', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_process_section',
				'process_section',
				'Engineering Delivery Process Accordion',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_proc_tag', 'section_tag', 'Section Eyebrow Tag', 'text' ),
						nt_make_field( 'field_nt_proc_title', 'section_title', 'Section Heading (H2)', 'text' ),
						nt_make_field( 'field_nt_proc_sub', 'section_subtitle', 'Section Intro', 'textarea', array( 'rows' => 2 ) ),
						nt_make_field( 'field_nt_proc_multi', 'allow_multiple', 'Allow Multiple Open Panels Simultaneously', 'true_false', array( 'default_value' => 1, 'ui' => 1 ) ),
						nt_make_field( 'field_nt_proc_steps', 'steps', 'Delivery Phases (4 Fixed Slots)', 'group', array( 'layout' => 'block', 'sub_fields' => $process_step_subfields ) ),
					),
				)
			),

			// TAB 7: FAQ SECTION
			nt_make_field( 'field_nt_tab_faq', '', 'FAQ Section', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_faq_section',
				'faq_section',
				'Frequently Asked Questions',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_faq_tag', 'section_tag', 'Section Eyebrow Tag', 'text' ),
						nt_make_field( 'field_nt_faq_title', 'section_title', 'Section Heading (H2)', 'text' ),
						nt_make_field( 'field_nt_faq_sub', 'section_subtitle', 'Section Intro', 'textarea', array( 'rows' => 2 ) ),
						nt_make_field( 'field_nt_faq_items', 'items', 'FAQ Items (5 Fixed Slots)', 'group', array( 'layout' => 'block', 'sub_fields' => $faq_item_subfields ) ),
					),
				)
			),

			// TAB 8: CONTACT & FOOTER
			nt_make_field( 'field_nt_tab_contact', '', 'Contact & Footer', 'tab', array( 'placement' => 'top' ) ),
			nt_make_field(
				'field_nt_contact_section',
				'contact_footer_section',
				'Contact Form 7 & Footer Settings',
				'group',
				array(
					'layout'     => 'block',
					'sub_fields' => array(
						nt_make_field( 'field_nt_cnt_tag', 'contact_tag', 'Contact Section Eyebrow', 'text' ),
						nt_make_field( 'field_nt_cnt_title', 'contact_title', 'Contact Section Heading (H2)', 'text' ),
						nt_make_field( 'field_nt_cnt_sub', 'contact_subtitle', 'Contact Intro Copy', 'textarea', array( 'rows' => 3 ) ),
						nt_make_field( 'field_nt_cnt_sla', 'response_sla_note', 'Response SLA Commitment', 'text' ),
						nt_make_field( 'field_nt_cnt_cf7', 'cf7_shortcode', 'Contact Form 7 Shortcode', 'text' ),
						nt_make_field( 'field_nt_cnt_email', 'company_email', 'Contact Email (Demo)', 'email' ),
						nt_make_field( 'field_nt_cnt_phone', 'company_phone', 'Phone Number (Demo)', 'text' ),
						nt_make_field( 'field_nt_cnt_addr', 'company_address', 'Headquarters Address (Demo)', 'text' ),
						nt_make_field( 'field_nt_cnt_hours', 'office_hours', 'Engineering Support Hours', 'text' ),
						nt_make_field( 'field_nt_ftr_desc', 'footer_description', 'Footer Brand Summary', 'textarea', array( 'rows' => 2 ) ),
						nt_make_field( 'field_nt_ftr_copy', 'copyright_text', 'Copyright Line', 'text' ),
						nt_make_field( 'field_nt_ftr_notice', 'demo_environment_notice', 'Demonstration Disclaimer', 'text' ),
					),
				)
			),
		),
		'location'              => array(
			array(
				array(
					'param'    => 'page_template',
					'operator' => '==',
					'value'    => 'page-novatech.php',
				),
			),
		),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen'        => array( 'the_content' ),
		'active'                => true,
		'description'           => 'Structured content schema for the NovaTech Solutions corporate landing page (ACF Free nested groups with WP REST API enabled).',
		'show_in_rest'          => 1,
	);
}

/**
 * Idempotently generate a technical PNG diagram or avatar in the WordPress Media Library.
 */
function nt_ensure_demo_image( $slug, $title, $alt_text, $width, $height, $palette, $caption_top, $caption_main, $caption_sub ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'name'           => $slug,
			'posts_per_page' => 1,
			'post_status'    => 'inherit',
		)
	);

	if ( ! empty( $existing ) ) {
		$att_id = $existing[0]->ID;
		update_post_meta( $att_id, '_wp_attachment_image_alt', $alt_text );
		return $att_id;
	}

	$upload_dir = wp_upload_dir();
	wp_mkdir_p( $upload_dir['path'] );
	$filename  = $slug . '.png';
	$file_path = trailingslashit( $upload_dir['path'] ) . $filename;

	$im = imagecreatetruecolor( $width, $height );
	imagesavealpha( $im, true );

	$bg       = imagecolorallocate( $im, $palette['bg'][0], $palette['bg'][1], $palette['bg'][2] );
	$surface  = imagecolorallocate( $im, $palette['surface'][0], $palette['surface'][1], $palette['surface'][2] );
	$accent   = imagecolorallocate( $im, $palette['accent'][0], $palette['accent'][1], $palette['accent'][2] );
	$accent2  = imagecolorallocate( $im, $palette['accent2'][0], $palette['accent2'][1], $palette['accent2'][2] );
	$grid_col = imagecolorallocate( $im, $palette['surface'][0] + 14, $palette['surface'][1] + 18, $palette['surface'][2] + 26 );
	$white    = imagecolorallocate( $im, 244, 247, 252 );
	$muted    = imagecolorallocate( $im, 156, 172, 198 );

	imagefilledrectangle( $im, 0, 0, $width, $height, $bg );

	// Subtle technical blueprint grid
	$step = max( 24, (int) ( $width / 20 ) );
	for ( $x = 0; $x < $width; $x += $step ) {
		imageline( $im, $x, 0, $x, $height, $grid_col );
	}
	for ( $y = 0; $y < $height; $y += $step ) {
		imageline( $im, 0, $y, $width, $y, $grid_col );
	}

	if ( $width > 300 ) {
		// Architectural node diagram card
		$pad = (int) ( $width * 0.07 );
		imagefilledrectangle( $im, $pad, $pad, $width - $pad, $height - $pad, $surface );
		imagerectangle( $im, $pad, $pad, $width - $pad, $height - $pad, $accent );

		// Accent header bar
		imagefilledrectangle( $im, $pad, $pad, $width - $pad, $pad + 8, $accent );

		// Draw 3 interconnected telemetry/service nodes
		$box_w = (int) ( ( $width - $pad * 2 - 80 ) / 3 );
		$box_y = (int) ( $height * 0.46 );
		$box_h = (int) ( $height * 0.34 );

		for ( $b = 0; $b < 3; $b++ ) {
			$bx1 = $pad + 20 + $b * ( $box_w + 20 );
			$bx2 = $bx1 + $box_w;
			imagefilledrectangle( $im, $bx1, $box_y, $bx2, $box_y + $box_h, $bg );
			imagerectangle( $im, $bx1, $box_y, $bx2, $box_y + $box_h, ( 1 === $b ? $accent2 : $accent ) );

			// Metric bars inside each node
			$bar_y = $box_y + 24;
			imagefilledrectangle( $im, $bx1 + 16, $bar_y, $bx2 - 28, $bar_y + 10, ( 1 === $b ? $accent2 : $accent ) );
			imagefilledrectangle( $im, $bx1 + 16, $bar_y + 22, $bx2 - 48, $bar_y + 30, $muted );
			imagefilledrectangle( $im, $bx1 + 16, $bar_y + 42, $bx2 - 36, $bar_y + 50, $grid_col );
		}

		imagestring( $im, 3, $pad + 24, $pad + 28, strtoupper( $caption_top ), $accent2 );
		imagestring( $im, 5, $pad + 24, $pad + 54, $caption_main, $white );
		imagestring( $im, 4, $pad + 24, $pad + 82, $caption_sub, $muted );
	} else {
		// Avatar badge for testimonials
		$cx = (int) ( $width / 2 );
		$cy = (int) ( $height / 2 );
		imagefilledellipse( $im, $cx, $cy, $width - 16, $height - 16, $surface );
		imageellipse( $im, $cx, $cy, $width - 16, $height - 16, $accent );
		imagefilledellipse( $im, $cx, $cy, $width - 42, $height - 42, $accent );
		$initials = $caption_main;
		$tx       = $cx - ( strlen( $initials ) * imagefontwidth( 5 ) / 2 );
		$ty       = $cy - ( imagefontheight( 5 ) / 2 );
		imagestring( $im, 5, (int) $tx, (int) $ty, $initials, $white );
	}

	imagepng( $im, $file_path, 6 );
	imagedestroy( $im );

	$attachment = array(
		'guid'           => trailingslashit( $upload_dir['url'] ) . $filename,
		'post_mime_type' => 'image/png',
		'post_title'     => $title,
		'post_name'      => $slug,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$att_id = wp_insert_attachment( $attachment, $file_path );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attach_data = wp_generate_attachment_metadata( $att_id, $file_path );
	wp_update_attachment_metadata( $att_id, $attach_data );
	update_post_meta( $att_id, '_wp_attachment_image_alt', $alt_text );

	return $att_id;
}

// ============================================================================
// STEP 1: Sync & Import ACF Field Group (Single Source of Truth)
// ============================================================================
echo "[seed] Configuring ACF Field Group 'NovaTech Corporate Landing Fields'...\n";

$acf_json_dir = get_stylesheet_directory() . '/acf-json';
wp_mkdir_p( $acf_json_dir );

$group_def  = nt_get_acf_field_group_definition();
$db_groups  = get_posts(
	array(
		'post_type'      => 'acf-field-group',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

$primary_group_id = 0;
foreach ( $db_groups as $dg ) {
	if ( 'group_novatech_landing' === $dg->post_name || 'novatech-corporate-landing-fields' === $dg->post_excerpt ) {
		if ( 0 === $primary_group_id ) {
			$primary_group_id = (int) $dg->ID;
		} else {
			wp_delete_post( $dg->ID, true );
		}
	}
}

if ( $primary_group_id > 0 ) {
	$group_def['ID'] = $primary_group_id;
}

if ( function_exists( 'acf_disable_filter' ) ) {
	acf_disable_filter( 'local' );
}
$imported_group = acf_import_field_group( $group_def );
if ( function_exists( 'acf_enable_filter' ) ) {
	acf_enable_filter( 'local' );
}
echo "[seed] ACF Field Group active (ID: {$imported_group['ID']}, Key: {$imported_group['key']}, show_in_rest: 1).\n";

// ============================================================================
// STEP 2: Generate Reproducible Local Media Assets
// ============================================================================
echo "[seed] Generating local PNG media assets in WordPress Media Library...\n";

$hero_img_id = nt_ensure_demo_image(
	'novatech-hero-architecture',
	'NovaTech Multi-Region Platform Topology',
	'Diagram of NovaTech multi-region Kubernetes, event streaming, and zero-trust API gateway topology',
	960,
	640,
	array(
		'bg'      => array( 11, 18, 33 ),
		'surface' => array( 19, 30, 52 ),
		'accent'  => array( 56, 189, 248 ),
		'accent2' => array( 52, 211, 153 ),
	),
	'SYSTEM TOPOLOGY // PROD-EU-CENTRAL',
	'Multi-Region Cloud & Telemetry Mesh',
	'99.98% Availability | p95 Latency < 18ms | Zero-Trust mTLS'
);

$case_images = array(
	1 => nt_ensure_demo_image(
		'novatech-case-fintech-ledger',
		'PayVantage Settlement Engine Architecture',
		'Settlement pipeline architecture diagram for PayVantage Clearing',
		720,
		450,
		array( 'bg' => array( 12, 21, 38 ), 'surface' => array( 22, 37, 64 ), 'accent' => array( 59, 130, 246 ), 'accent2' => array( 45, 212, 191 ) ),
		'FINTECH CASE STUDY // DEMO',
		'Event-Driven Ledger Settlement',
		'42,000 tx/sec peak throughput with deterministic reconciliation'
	),
	2 => nt_ensure_demo_image(
		'novatech-case-freight-telemetry',
		'NorthStar Logistics Real-Time Routing Mesh',
		'IoT telemetry and cold-chain routing dashboard diagram for NorthStar Logistics',
		720,
		450,
		array( 'bg' => array( 14, 24, 34 ), 'surface' => array( 23, 41, 56 ), 'accent' => array( 16, 185, 129 ), 'accent2' => array( 56, 189, 248 ) ),
		'SUPPLY CHAIN CASE STUDY // DEMO',
		'Cold-Chain Telemetry & Route Engine',
		'Sub-second sensor ingestion across 18,500 active fleet units'
	),
	3 => nt_ensure_demo_image(
		'novatech-case-healthtech-fhir',
		'Veritas Diagnostics FHIR Data Exchange',
		'HIPAA-compliant clinical data pipeline diagram for Veritas Diagnostics',
		720,
		450,
		array( 'bg' => array( 17, 20, 40 ), 'surface' => array( 28, 34, 66 ), 'accent' => array( 99, 102, 241 ), 'accent2' => array( 52, 211, 153 ) ),
		'HEALTHTECH CASE STUDY // DEMO',
		'Zero-Trust Clinical FHIR Gateway',
		'Automated audit trails and 64% faster lab report delivery'
	),
	4 => nt_ensure_demo_image(
		'novatech-case-energy-grid',
		'Helios Grid Forecasting Platform',
		'Distributed renewable load balancing platform diagram for Helios Grid',
		720,
		450,
		array( 'bg' => array( 15, 23, 30 ), 'surface' => array( 26, 39, 50 ), 'accent' => array( 245, 158, 11 ), 'accent2' => array( 56, 189, 248 ) ),
		'ENERGY GRID CASE STUDY // DEMO',
		'Grid Load Forecasting Pipeline',
		'15-minute interval demand prediction across 1.4M smart meters'
	),
	5 => nt_ensure_demo_image(
		'novatech-case-saas-idp',
		'CloudScale Internal Developer Platform',
		'Self-service Kubernetes platform diagram for CloudScale B2B SaaS',
		720,
		450,
		array( 'bg' => array( 16, 19, 34 ), 'surface' => array( 29, 35, 58 ), 'accent' => array( 14, 165, 233 ), 'accent2' => array( 168, 85, 247 ) ),
		'SAAS PLATFORM CASE STUDY // DEMO',
		'Self-Service Developer Platform',
		'Environment provisioning reduced from 6 days to 14 minutes'
	),
);

$avatar_images = array(
	1 => nt_ensure_demo_image(
		'novatech-avatar-elena-rostova',
		'Portrait initials of Elena Vance',
		'Avatar badge for Elena Vance, VP of Platform Engineering at PayVantage Clearing',
		160,
		160,
		array( 'bg' => array( 15, 23, 42 ), 'surface' => array( 30, 41, 59 ), 'accent' => array( 37, 99, 235 ), 'accent2' => array( 56, 189, 248 ) ),
		'',
		'EV',
		''
	),
	2 => nt_ensure_demo_image(
		'novatech-avatar-marcus-thorne',
		'Portrait initials of Marcus Thorne',
		'Avatar badge for Marcus Thorne, Chief Technology Officer at NorthStar Freight Systems',
		160,
		160,
		array( 'bg' => array( 15, 23, 42 ), 'surface' => array( 30, 41, 59 ), 'accent' => array( 5, 150, 105 ), 'accent2' => array( 52, 211, 153 ) ),
		'',
		'MT',
		''
	),
	3 => nt_ensure_demo_image(
		'novatech-avatar-claire-bennett',
		'Portrait initials of Dr. Claire Bennett',
		'Avatar badge for Dr. Claire Bennett, Head of Clinical Informatics at Veritas Diagnostics',
		160,
		160,
		array( 'bg' => array( 15, 23, 42 ), 'surface' => array( 30, 41, 59 ), 'accent' => array( 79, 70, 229 ), 'accent2' => array( 129, 140, 248 ) ),
		'',
		'CB',
		''
	),
	4 => nt_ensure_demo_image(
		'novatech-avatar-david-keller',
		'Portrait initials of David Keller',
		'Avatar badge for David Keller, SVP of Infrastructure at Helios Grid Operations',
		160,
		160,
		array( 'bg' => array( 15, 23, 42 ), 'surface' => array( 30, 41, 59 ), 'accent' => array( 217, 119, 6 ), 'accent2' => array( 251, 191, 36 ) ),
		'',
		'DK',
		''
	),
);

// ============================================================================
// STEP 3: Create or Update Contact Form 7 Form ("NovaTech Project Inquiry")
// ============================================================================
echo "[seed] Configuring Contact Form 7 form 'NovaTech Project Inquiry'...\n";

// Remove unused default "Contact form 1" created by CF7 upon initial activation
$default_cf7 = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'name'           => 'contact-form-1',
		'posts_per_page' => -1,
		'post_status'    => 'any',
	)
);
foreach ( $default_cf7 as $dcf ) {
	wp_delete_post( $dcf->ID, true );
}

$cf7_posts = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'name'           => 'novatech-project-inquiry',
		'posts_per_page' => 1,
		'post_status'    => 'any',
	)
);

$cf7_form_template = <<<'CF7HTML'
<div class="nt-form-grid">
  <div class="nt-form-field">
    <label for="nt-your-name">Full Name <span class="nt-required" aria-hidden="true">*</span></label>
    [text* your-name id:nt-your-name autocomplete:name placeholder "e.g., Alex Morgan"]
  </div>
  <div class="nt-form-field">
    <label for="nt-your-email">Work Email <span class="nt-required" aria-hidden="true">*</span></label>
    [email* your-email id:nt-your-email autocomplete:email placeholder "alex.morgan@company.example"]
  </div>
  <div class="nt-form-field nt-form-field--full">
    <label for="nt-your-subject">Project Scope or Inquiry Topic <span class="nt-required" aria-hidden="true">*</span></label>
    [text* your-subject id:nt-your-subject placeholder "e.g., Cloud Platform Consolidation & Cost Audit"]
  </div>
  <div class="nt-form-field nt-form-field--full">
    <label for="nt-your-message">Technical Context & Timeline <span class="nt-required" aria-hidden="true">*</span></label>
    [textarea* your-message id:nt-your-message x4 placeholder "Briefly describe your current architecture, key bottlenecks, and target delivery window."]
  </div>
  <div class="nt-form-actions nt-form-field--full">
    [submit class:nt-btn class:nt-btn--primary "Request Architecture Consultation"]
    <p class="nt-form-privacy">Demo environment notice: submissions are routed exclusively to the local Mailpit container (127.0.0.1:8025) and never leave your machine.</p>
  </div>
</div>
CF7HTML;

$cf7_mail_meta = array(
	'active'             => true,
	'subject'            => '[NovaTech Lab Inquiry] [your-subject]',
	'sender'             => 'NovaTech Solutions Demo <no-reply@novatech.local>',
	'recipient'          => 'architecture-desk@novatech.local',
	'body'               => "New project inquiry submitted via NovaTech Solutions landing page (Local Lab Environment):\n\n"
		. "Name: [your-name]\n"
		. "Work Email: [your-email]\n"
		. "Subject: [your-subject]\n\n"
		. "Technical Context & Message:\n[your-message]\n\n"
		. "--\nDelivered to local Mailpit container via WP-to-Astro Lab.",
	'additional_headers' => 'Reply-To: [your-email]',
	'attachments'        => '',
	'use_html'           => false,
	'exclude_blank'      => false,
);

$cf7_messages = array(
	'mail_sent_ok'             => 'Thank you. Your technical inquiry has been delivered to our local architecture desk (verified in Mailpit).',
	'mail_sent_ng'             => 'We could not deliver your message due to a server transport error. Please verify that the local Mailpit service is running.',
	'validation_error'         => 'One or more fields require attention. Please review the highlighted inputs below.',
	'spam'                     => 'Submission blocked by automated security filters.',
	'accept_terms'             => 'Please confirm the required terms before submitting.',
	'invalid_required'         => 'This field is required.',
	'invalid_too_long'         => 'The entered text exceeds the maximum allowed length.',
	'invalid_too_short'        => 'The entered text is shorter than the minimum required length.',
	'invalid_email'            => 'Please enter a valid work email address.',
);

if ( empty( $cf7_posts ) ) {
	$cf7_id = wp_insert_post(
		array(
			'post_type'   => 'wpcf7_contact_form',
			'post_name'   => 'novatech-project-inquiry',
			'post_title'  => 'NovaTech Project Inquiry',
			'post_status' => 'publish',
		)
	);
} else {
	$cf7_id = $cf7_posts[0]->ID;
	wp_update_post(
		array(
			'ID'          => $cf7_id,
			'post_title'  => 'NovaTech Project Inquiry',
			'post_status' => 'publish',
		)
	);
}

update_post_meta( $cf7_id, '_form', $cf7_form_template );
update_post_meta( $cf7_id, '_mail', $cf7_mail_meta );
update_post_meta( $cf7_id, '_messages', $cf7_messages );
update_post_meta( $cf7_id, '_locale', 'en_US' );

$cf7_shortcode = sprintf( '[contact-form-7 id="%d" title="NovaTech Project Inquiry"]', $cf7_id );
echo "[seed] Contact Form 7 ready (ID: {$cf7_id}, Shortcode: {$cf7_shortcode}).\n";

// ============================================================================
// STEP 4: Create or Update the "NovaTech Solutions" Landing Page & ACF Data
// ============================================================================
echo "[seed] Creating or updating the 'NovaTech Solutions' landing page...\n";

$existing_pages = get_posts(
	array(
		'post_type'      => 'page',
		'name'           => 'novatech-solutions',
		'posts_per_page' => 1,
		'post_status'    => 'any',
	)
);

if ( empty( $existing_pages ) ) {
	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_name'    => 'novatech-solutions',
			'post_title'   => 'NovaTech Solutions',
			'post_content' => '<!-- Content managed via Advanced Custom Fields (NovaTech Corporate Landing Fields) -->',
			'post_status'  => 'publish',
		)
	);
} else {
	$page_id = $existing_pages[0]->ID;
	wp_update_post(
		array(
			'ID'          => $page_id,
			'post_title'  => 'NovaTech Solutions',
			'post_status' => 'publish',
		)
	);
}

update_post_meta( $page_id, '_wp_page_template', 'page-novatech.php' );

// Populate all 8 ACF sections with concrete B2B engineering copy
$acf_payload = array(
	'header_settings'        => array(
		'logo_text'         => 'NovaTech',
		'logo_badge'        => 'Solutions',
		'announcement_text' => 'DEMONSTRATION LAB ENVIRONMENT // WORDPRESS 6.8 + ACF FREE BASELINE FOR ASTRO MIGRATION',
		'cta_label'         => 'Schedule Architecture Review',
		'cta_url'           => '#contact',
		'nav_items'         => array(
			'nav_1' => array( 'enabled' => 1, 'label' => 'Services', 'url' => '#services' ),
			'nav_2' => array( 'enabled' => 1, 'label' => 'Case Studies', 'url' => '#cases' ),
			'nav_3' => array( 'enabled' => 1, 'label' => 'Client Outcomes', 'url' => '#testimonials' ),
			'nav_4' => array( 'enabled' => 1, 'label' => 'Delivery Model', 'url' => '#process' ),
			'nav_5' => array( 'enabled' => 1, 'label' => 'FAQ', 'url' => '#faq' ),
		),
	),

	'hero_section'           => array(
		'eyebrow_badge'        => 'Cloud Platform, Data & Reliability Engineering',
		'title'                => 'Resilient cloud infrastructure and data platforms built for predictable scale.',
		'subtitle'             => 'NovaTech Solutions partners with regulated enterprises and high-growth B2B software teams to modernize legacy workloads, reduce cloud unit costs, and ship auditable production systems without operational downtime.',
		'primary_cta_text'     => 'Request Technical Scoping',
		'primary_cta_url'      => '#contact',
		'secondary_cta_text'   => 'Explore Engineering Case Studies',
		'secondary_cta_url'    => '#cases',
		'hero_image'           => $hero_img_id,
		'architecture_caption' => 'Reference Architecture: Multi-region Kubernetes control plane with automated failover and OpenTelemetry observability (Fictional Demo Data).',
		'stats'                => array(
			'stat_1' => array(
				'value'  => '99.98%',
				'label'  => 'Measured Production SLA',
				'detail' => 'Across 40+ managed multi-region clusters (Demo metric)',
			),
			'stat_2' => array(
				'value'  => '34%',
				'label'  => 'Average Cloud Spend Reduction',
				'detail' => 'Achieved within 90 days via compute right-sizing & spot orchestration',
			),
			'stat_3' => array(
				'value'  => '< 18 ms',
				'label'  => 'p95 API Gateway Latency',
				'detail' => 'For high-throughput transactional workloads in EU & US regions',
			),
		),
	),

	'services_section'       => array(
		'section_tag'      => 'CAPABILITIES & DELIVERABLES',
		'section_title'    => 'Engineering practices designed around measurable operational outcomes',
		'section_subtitle' => 'We do not sell generic staffing hours. Each engagement delivers production-grade infrastructure code, automated runbooks, and full knowledge transfer to your internal teams.',
		'cards'            => array(
			'card_1' => array(
				'enabled'               => 1,
				'icon_key'              => 'cloud',
				'badge'                 => 'Infrastructure & FinOps',
				'title'                 => 'Cloud Architecture & Cost Governance',
				'description'           => 'We audit and refactor AWS, GCP, and Azure environments using Terraform and OpenTofu - eliminating idle provisioning, over-privileged IAM roles, and cross-AZ data transfer bottlenecks.',
				'deliverable_highlight' => 'Deliverable: Multi-account Terraform modules + automated FinOps cost anomaly alerts',
				'link_text'             => 'Review cloud scope',
				'link_url'              => '#contact',
			),
			'card_2' => array(
				'enabled'               => 1,
				'icon_key'              => 'data',
				'badge'                 => 'Real-Time Data',
				'title'                 => 'Event Streaming & Lakehouse Pipelines',
				'description'           => 'Replace brittle overnight batch scripts with fault-tolerant Kafka, Debezium CDC, and ClickHouse or Snowflake pipelines with strict schema contracts and data lineage.',
				'deliverable_highlight' => 'Deliverable: Sub-minute CDC ingestion pipelines + automated data quality gates',
				'link_text'             => 'Explore data architectures',
				'link_url'              => '#cases',
			),
			'card_3' => array(
				'enabled'               => 1,
				'icon_key'              => 'platform',
				'badge'                 => 'Developer Experience',
				'title'                 => 'Internal Developer Platforms (IDP)',
				'description'           => 'Standardize how product teams deploy services with golden CI/CD templates, ArgoCD GitOps workflows, ephemeral preview environments, and unified OpenTelemetry tracing.',
				'deliverable_highlight' => 'Deliverable: Self-service service catalog + GitOps deployment pipelines',
				'link_text'             => 'See platform workflow',
				'link_url'              => '#process',
			),
			'card_4' => array(
				'enabled'               => 1,
				'icon_key'              => 'security',
				'badge'                 => 'Compliance & Hardening',
				'title'                 => 'Zero-Trust Security & Compliance Automation',
				'description'           => 'Embed SOC 2 Type II, ISO 27001, and HIPAA controls directly into your CI/CD pipeline with automated policy-as-code (OPA), container signing, and secrets rotation.',
				'deliverable_highlight' => 'Deliverable: Continuous compliance evidence collector + hardened runtime policies',
				'link_text'             => 'Inspect security controls',
				'link_url'              => '#faq',
			),
			'card_5' => array(
				'enabled'               => 1,
				'icon_key'              => 'ai',
				'badge'                 => 'Production AI Systems',
				'title'                 => 'Private Retrieval & Applied ML Infrastructure',
				'description'           => 'Deploy deterministic document retrieval (RAG), vector indexing, and model-serving gateways inside your private VPC with strict token budgets, latency SLAs, and PII redaction.',
				'deliverable_highlight' => 'Deliverable: VPC-isolated inference gateway + automated evaluation benchmark suite',
				'link_text'             => 'Discuss AI deployment',
				'link_url'              => '#contact',
			),
			'card_6' => array(
				'enabled'               => 1,
				'icon_key'              => 'modernization',
				'badge'                 => 'Legacy Decoupling',
				'title'                 => 'Monolith Decomposition & Composable Web',
				'description'           => 'Incrementally strangle high-maintenance legacy monoliths and slow CMS installations into fast, statically generated or edge-rendered architectures without breaking SEO or integrations.',
				'deliverable_highlight' => 'Deliverable: Phased strangler-fig migration + zero-downtime routing cutover',
				'link_text'             => 'Plan a migration',
				'link_url'              => '#contact',
			),
		),
	),

	'cases_slider_section'   => array(
		'section_tag'      => 'SELECTED ENGAGEMENTS (DEMO DATA)',
		'section_title'    => 'Verified engineering outcomes across regulated industries',
		'section_subtitle' => 'Use the controls or swipe to inspect how our architecture teams solve throughput, reliability, and infrastructure cost challenges.',
		'demo_disclaimer'  => 'All client names, architectures, and metrics below are fictional engineering scenarios created for the WP-to-Astro migration lab.',
		'slides'           => array(
			'slide_1' => array(
				'enabled'          => 1,
				'category'         => 'FinTech',
				'client_name'      => 'PayVantage Clearing (Demo Client)',
				'title'            => 'Migrating a monolithic SQL settlement core to an event-sourced ledger',
				'summary'          => 'Decomposed a single-threaded overnight batch clearing database into partitioned Kafka streams and idempotent Go microservices with zero transaction loss during cutover.',
				'metric_value'     => '42,000 tx/s',
				'metric_label'     => 'Sustained peak settlement throughput',
				'secondary_metric' => '68% reduction in end-of-day reconciliation window',
				'tech_stack'       => 'Go | Apache Kafka | PostgreSQL 16 | Kubernetes | OpenTelemetry',
				'image'            => $case_images[1],
				'case_url'         => '#contact',
			),
			'slide_2' => array(
				'enabled'          => 1,
				'category'         => 'Supply Chain',
				'client_name'      => 'NorthStar Freight Systems (Demo Client)',
				'title'            => 'Real-time cold-chain telemetry ingestion for 18,500 refrigerated containers',
				'summary'          => 'Replaced polling-based regional servers with an MQTT edge broker mesh and ClickHouse time-series analytics to detect temperature excursion anomalies in under 3 seconds.',
				'metric_value'     => '2.4 sec',
				'metric_label'     => 'End-to-end sensor alert latency (p99)',
				'secondary_metric' => '$1.1M annual savings in prevented cargo spoilage (Demo)',
				'tech_stack'       => 'Rust | EMQX (MQTT) | ClickHouse | Terraform | AWS IoT',
				'image'            => $case_images[2],
				'case_url'         => '#contact',
			),
			'slide_3' => array(
				'enabled'          => 1,
				'category'         => 'HealthTech',
				'client_name'      => 'Veritas Clinical Diagnostics (Demo Client)',
				'title'            => 'Zero-trust HL7 FHIR data exchange across 90 regional laboratories',
				'summary'          => 'Engineered an mTLS-authenticated API gateway and automated PHI field-level encryption pipeline, accelerating compliance audits from six weeks to automated daily reports.',
				'metric_value'     => '64% faster',
				'metric_label'     => 'Diagnostic report turnaround time',
				'secondary_metric' => '100% automated HIPAA & SOC 2 audit evidence generation',
				'tech_stack'       => 'TypeScript | Envoy Proxy | HashiCorp Vault | AWS ECS | OPA',
				'image'            => $case_images[3],
				'case_url'         => '#contact',
			),
			'slide_4' => array(
				'enabled'          => 1,
				'category'         => 'Energy Grid',
				'client_name'      => 'Helios Grid Operations (Demo Client)',
				'title'            => 'Distributed load forecasting for 1.4 million residential smart meters',
				'summary'          => 'Built a fault-tolerant feature store and streaming aggregation pipeline that ingests 15-minute interval readings to balance solar and wind feed-in tariffs automatically.',
				'metric_value'     => '38% lower',
				'metric_label'     => 'Compute cost per billion metered events',
				'secondary_metric' => '99.99% pipeline availability during peak storm events',
				'tech_stack'       => 'Python | Apache Flink | Apache Iceberg | Kubernetes | Prometheus',
				'image'            => $case_images[4],
				'case_url'         => '#contact',
			),
			'slide_5' => array(
				'enabled'          => 1,
				'category'         => 'SaaS Infrastructure',
				'client_name'      => 'CloudScale Workspaces (Demo Client)',
				'title'            => 'Self-service Kubernetes platform for 180 product engineers',
				'summary'          => 'Replaced manual DevOps ticket queues with backstage service templates, ArgoCD GitOps, and automated preview environments for every pull request.',
				'metric_value'     => '14 mins',
				'metric_label'     => 'New microservice provisioning time (down from 6 days)',
				'secondary_metric' => '4.2x increase in weekly production deployment frequency',
				'tech_stack'       => 'ArgoCD | Backstage | Crossplane | GitHub Actions | Grafana',
				'image'            => $case_images[5],
				'case_url'         => '#contact',
			),
		),
	),

	'testimonials_section'   => array(
		'section_tag'      => 'CLIENT PERSPECTIVES (DEMO)',
		'section_title'    => 'Why engineering leaders trust NovaTech with critical systems',
		'section_subtitle' => 'Fictional executive feedback illustrating typical stakeholder outcomes across infrastructure modernization and platform delivery.',
		'autoplay_delay'   => 6000,
		'slides'           => array(
			'slide_1' => array(
				'enabled'          => 1,
				'quote'            => 'NovaTech replaced our fragile overnight settlement scripts with an event-driven ledger without a single minute of merchant downtime. More importantly, their engineers documented every runbook and trained our internal SRE team to own the platform.',
				'author_name'      => 'Elena Vance',
				'author_role'      => 'VP of Platform Engineering',
				'company'          => 'PayVantage Clearing (Demo)',
				'engagement_scope' => 'Core Ledger Modernization | 16-Week Engagement',
				'rating'           => 5,
				'avatar'           => $avatar_images[1],
			),
			'slide_2' => array(
				'enabled'          => 1,
				'quote'            => 'Within six weeks of their cloud architecture audit, we cut our monthly AWS bill by 36% while doubling our telemetry ingestion capacity. Every recommendation came as tested Terraform code rather than a slide deck.',
				'author_name'      => 'Marcus Thorne',
				'author_role'      => 'Chief Technology Officer',
				'company'          => 'NorthStar Freight Systems (Demo)',
				'engagement_scope' => 'IoT Telemetry & FinOps Overhaul | 12-Week Engagement',
				'rating'           => 5,
				'avatar'           => $avatar_images[2],
			),
			'slide_3' => array(
				'enabled'          => 1,
				'quote'            => 'Passing our healthcare security audits used to freeze feature development for two months every year. NovaTech embedded policy-as-code checks directly into our CI pipelines so compliance verification happens automatically on every commit.',
				'author_name'      => 'Dr. Claire Bennett',
				'author_role'      => 'Head of Clinical Informatics',
				'company'          => 'Veritas Clinical Diagnostics (Demo)',
				'engagement_scope' => 'Zero-Trust FHIR Gateway | 14-Week Engagement',
				'rating'           => 5,
				'avatar'           => $avatar_images[3],
			),
			'slide_4' => array(
				'enabled'          => 1,
				'quote'            => 'Our product teams used to wait nearly a week for infrastructure provisioning. The GitOps platform NovaTech built lets any developer spin up an isolated staging stack in under fifteen minutes with built-in cost limits.',
				'author_name'      => 'David Keller',
				'author_role'      => 'SVP of Infrastructure',
				'company'          => 'Helios Grid Operations (Demo)',
				'engagement_scope' => 'Internal Developer Platform | 10-Week Engagement',
				'rating'           => 5,
				'avatar'           => $avatar_images[4],
			),
		),
	),

	'process_section'        => array(
		'section_tag'      => 'DELIVERY METHODOLOGY',
		'section_title'    => 'How we take complex architecture from discovery to production handoff',
		'section_subtitle' => 'Each phase has explicit entry criteria, architectural decision records (ADRs), and verifiable production gates. You can expand multiple phases simultaneously to compare deliverables.',
		'allow_multiple'   => 1,
		'steps'            => array(
			'step_1' => array(
				'enabled'         => 1,
				'step_number'     => '01',
				'title'           => 'Architecture Discovery & Baseline Telemetry Audit',
				'duration_badge'  => 'Weeks 1-2',
				'summary'         => 'We instrument your existing workloads, trace production bottlenecks, analyze cloud billing line-items, and map security boundaries before writing a single line of migration code.',
				'deliverables'    => "Current-state system topology & dependency map\nCloud unit-cost & bottleneck analysis report\nPrioritized Risk Register & Architecture Decision Records (ADRs)\nFixed-scope implementation roadmap with rollback milestones",
				'governance_note' => 'Gate 01: Executive & Principal Architect sign-off on target SLA metrics and budget ceiling.',
				'default_open'    => 1,
			),
			'step_2' => array(
				'enabled'         => 1,
				'step_number'     => '02',
				'title'           => 'Foundation Blueprint & Infrastructure-as-Code',
				'duration_badge'  => 'Weeks 3-5',
				'summary'         => 'We provision isolated landing zones, modular Terraform/OpenTofu stacks, CI/CD pipelines, and automated security policy checks inside your version control system.',
				'deliverables'    => "Versioned Terraform / OpenTofu infrastructure modules\nAutomated CI/CD pipelines with OPA security & compliance gates\nOpenTelemetry collector mesh, Grafana dashboards & PagerDuty alert routing\nDisaster recovery and automated backup verification scripts",
				'governance_note' => 'Gate 02: Automated penetration test & chaos failover drill in staging environment.',
				'default_open'    => 1,
			),
			'step_3' => array(
				'enabled'         => 1,
				'step_number'     => '03',
				'title'           => 'Incremental Migration & Shadow Traffic Validation',
				'duration_badge'  => 'Weeks 6-10',
				'summary'         => 'Instead of high-risk "big bang" cutovers, we use strangler-fig routing and dual-write shadow traffic to validate performance, data parity, and latency under real production load.',
				'deliverables'    => "Canary & blue/green traffic routing configuration\nAutomated data parity verifiers between legacy and target stores\nLoad testing benchmarks at 3x historical peak traffic\nZero-downtime production cutover execution",
				'governance_note' => 'Gate 03: 100% data reconciliation parity & p95 latency verification across 72 hours of live load.',
				'default_open'    => 0,
			),
			'step_4' => array(
				'enabled'         => 1,
				'step_number'     => '04',
				'title'           => 'Operational Handover, Runbooks & Team Enablement',
				'duration_badge'  => 'Weeks 11-12',
				'summary'         => 'We ensure your internal engineering team has full ownership - conducting live game-day incident simulations, pair-programming sessions, and comprehensive documentation handoff.',
				'deliverables'    => "Operational runbooks & incident response playbooks\nRecorded architecture walkthroughs & live SRE game-day drills\n30-day post-cutover hypercare support SLA\nComplete removal of legacy decommissioned infrastructure",
				'governance_note' => 'Gate 04: Internal team independently executes deployment, rollback, and incident recovery drills.',
				'default_open'    => 0,
			),
		),
	),

	'faq_section'            => array(
		'section_tag'      => 'TECHNICAL FAQ',
		'section_title'    => 'Common questions about engagement structure, security, and ownership',
		'section_subtitle' => 'Clear answers regarding how our engineering teams integrate with your organization.',
		'items'            => array(
			'faq_1' => array(
				'enabled'        => 1,
				'category_badge' => 'Code Ownership',
				'question'       => 'Who owns the infrastructure modules, scripts, and architectural code produced during an engagement?',
				'answer'         => 'Your organization retains 100% intellectual property and source code ownership from day one. All code is committed directly to your private Git repositories under your license, with zero proprietary lock-in or recurring licensing fees to NovaTech.',
				'default_open'   => 1,
			),
			'faq_2' => array(
				'enabled'        => 1,
				'category_badge' => 'Zero-Downtime Cutover',
				'question'       => 'How do you prevent service disruption when migrating live databases or legacy monoliths?',
				'answer'         => 'We use change-data-capture (CDC) replication and dual-read/dual-write proxy layers so the new system processes shadow traffic alongside your legacy stack. Traffic is shifted in 1%, 10%, 50%, and 100% increments with instant automated rollback triggers if error budgets are exceeded.',
				'default_open'   => 0,
			),
			'faq_3' => array(
				'enabled'        => 1,
				'category_badge' => 'Security & Access',
				'question'       => 'How do your engineers handle access to regulated production environments (SOC 2, HIPAA, PCI-DSS)?',
				'answer'         => 'We operate under strict least-privilege principles. Our engineers never store long-lived credentials; all work is performed through your federated SSO with short-lived bastion sessions, mandatory MFA, and full audit logging of every CLI command and pull request.',
				'default_open'   => 0,
			),
			'faq_4' => array(
				'enabled'        => 1,
				'category_badge' => 'Team Collaboration',
				'question'       => 'Do you replace our internal DevOps or platform team, or work alongside them?',
				'answer'         => 'We work as a force multiplier alongside your internal engineers. Our goal is to eliminate your architectural backlog and build self-service tooling so your team can focus on core product features rather than firefighting infrastructure tickets.',
				'default_open'   => 0,
			),
			'faq_5' => array(
				'enabled'        => 1,
				'category_badge' => 'Commercial Model',
				'question'       => 'How are engagements priced and scoped?',
				'answer'         => 'Every project begins with a fixed-price 2-week Architecture Discovery audit. Following discovery, we provide a milestone-based fixed-scope proposal tied to verifiable engineering deliverables rather than open-ended hourly billing.',
				'default_open'   => 0,
			),
		),
	),

	'contact_footer_section' => array(
		'contact_tag'             => 'START A TECHNICAL CONVERSATION',
		'contact_title'           => 'Request an architecture review or migration feasibility audit',
		'contact_subtitle'        => 'Share your current infrastructure context below. A Principal Systems Architect will review your note and respond with a proposed technical agenda.',
		'response_sla_note'       => 'Direct engineering response within 1 business day - no sales development reps.',
		'cf7_shortcode'           => $cf7_shortcode,
		'company_email'           => 'architecture-desk@novatech.local',
		'company_phone'           => '+1 (415) 555-0194 (Demo Line)',
		'company_address'         => '450 Mission Street, Suite 1400, San Francisco, CA 94105 (Fictional HQ)',
		'office_hours'            => 'Monday - Friday, 08:00 - 18:00 UTC / PST',
		'footer_description'      => 'NovaTech Solutions is a fictional B2B cloud and platform engineering consultancy created for the WordPress-to-Astro migration laboratory.',
		'copyright_text'          => '(c) 2026 NovaTech Solutions Lab. All rights reserved.',
		'demo_environment_notice' => 'Educational Reference Project: All client names, case studies, metrics, and contact details on this page are fictional.',
	),
);

foreach ( $acf_payload as $field_name => $field_value ) {
	update_field( $field_name, $field_value, $page_id );
}

// Flush rewrite rules so /novatech-solutions/ resolves cleanly
flush_rewrite_rules( true );

echo "[seed] Page 'NovaTech Solutions' (ID: {$page_id}) updated with complete ACF payload.\n";
echo "[seed] URL: " . get_permalink( $page_id ) . "\n";
