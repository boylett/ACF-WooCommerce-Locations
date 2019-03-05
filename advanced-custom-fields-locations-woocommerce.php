<?php

	/**
	 * Plugin Name: Advanced Custom Fields - WooCommerce Locations
	 * Plugin URI:  https://github.com/boylett/acf-woocommerce-locations
	 * Description: Adds various WooCommerce pages as ACF locations
	 * Author:      Ryan Boylett
	 * Author URI:  https://github.com/boylett
	 * Version:     0.0.1
	 */

	defined("ABSPATH") || exit;

	add_action('admin_init', function()
	{
		if(function_exists('acf_form_head') and isset($_REQUEST['page']) and $_REQUEST['page'] == 'wc-settings')
		{
			acf_form_head();
		}
	});

	add_action('woocommerce_settings_tabs', function()
	{
		if(function_exists('acf_get_field_groups'))
		{
			global $current_tab, $current_acf_field_tab, $current_acf_field_group;

			if(preg_match("/^acf-custom-field-group-([0-9]+)/i", $current_tab, $group_id))
			{
				preg_match("/^acf-custom-field-group-([0-9]+)-([0-9]+)/i", $current_tab, $tab_id);

				$current_tab             = 'acf-custom-field-group';
				$current_acf_field_tab   = isset($tab_id[2]) ? $tab_id[2] : NULL;
				$current_acf_field_group = $group_id[1];
			}
			else
			{
				$all_field_groups = acf_get_field_groups();

				foreach($all_field_groups as $field_group)
				{
					foreach($field_group['location'] as $rules)
					{
						foreach($rules as $rule)
						{
							if($rule['param'] == 'wc_settings_page' and $rule['operator'] == '==' and $rule['value'] == $current_tab)
							{
								add_action('woocommerce_settings_' . $current_tab, function() use($field_group)
								{
									global $current_acf_field_tab, $current_acf_field_group;

									$current_acf_field_tab   = NULL;
									$current_acf_field_group = $field_group['key'];

									echo '<h2>' . $field_group['title'] . '</h2>';

									do_action('woocommerce_settings_acf-custom-field-group');
								}, 1000);

								break 3;
							}
						}
					}
				}
			}
		}
	});

	add_filter('woocommerce_settings_tabs_array', function($tabs)
	{
		if(function_exists('acf_get_field_groups'))
		{
			global $current_tab, $wpdb;

			$all_field_groups = acf_get_field_groups();

			foreach($all_field_groups as $field_group)
			{
				foreach($field_group['location'] as $rules)
				{
					foreach($rules as $rule)
					{
						if($rule['param'] == 'wc_settings_page' and $rule['operator'] == '==' and $rule['value'] == 'default')
						{
							$inner_tabs = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_content LIKE '%\"type\";s:3:\"tab\"%' AND post_parent = {$field_group['ID']} AND post_status = 'publish' AND post_type = 'acf-field' ORDER BY menu_order ASC");

							if(empty($inner_tabs))
							{
								$tabs['acf-custom-field-group-' . $field_group['ID']] = $field_group['title'];
							}
							else
							{
								foreach($inner_tabs as $tab_field)
								{
									$tabs['acf-custom-field-group-' . $field_group['ID'] . '-' . $tab_field->ID] = $tab_field->post_title;
								}
							}
							
							continue 3;
						}
					}
				}
			}
		}

		return $tabs;
	}, 1000, 1);

	add_action('woocommerce_settings_acf-custom-field-group', function()
	{
		if(function_exists('acf_form'))
		{
			global $current_acf_field_tab, $current_acf_field_group, $wpdb;

			ob_start();

			$form = array
			(
				"field_el"              => "tr",
				"form"                  => false,
				"html_before_fields"    => '<table class="form-table"><tbody>',
				"html_after_fields"     => '</tbody></table>',
				"id"                    => "acf-custom-field-group",
				"instruction_placement" => "field",
				"post_id"               => "options",
			);

			if($current_acf_field_tab)
			{
				$fields          = $wpdb->get_results("SELECT ID, post_name, post_content FROM {$wpdb->posts} WHERE post_parent = {$current_acf_field_group} AND post_status = 'publish' AND post_type = 'acf-field' ORDER BY menu_order ASC");
				$included_open   = false;
				$included_fields = array();

				foreach($fields as $field)
				{
					if($field->ID == $current_acf_field_tab)
					{
						$included_open = true;

						continue;
					}
					else if($included_open and preg_match("/\"type\";s:3:\"tab\"/i", $field->post_content))
					{
						break;
					}

					if($included_open)
					{
						$included_fields[] = $field->post_name;
					}
				}

				$form['fields'] = $included_fields;
			}
			else
			{
				$form['field_groups'] = array($current_acf_field_group);
			}

			acf_form($form);

			$form = ob_get_clean();
			$form = preg_replace("/<td class=\"acf-label([\s\S]+?)<\/td>/i", "<th class=\"acf-label$1</th>", $form);

			echo $form;
		}
	});

	add_filter('acf/location/rule_types', function($choices)
	{
		$choices['WooCommerce']['wc_settings_page'] = 'Settings Page';

		return $choices;
	});

	add_filter('acf/location/rule_values/wc_settings_page', function($choices)
	{
		$choices = array
		(
			"default"     => "– New Tab –",
			'general'     => __('General', 'woocommerce') . " Tab",
			'products'    => __('Products', 'woocommerce') . " Tab",
			'tax'         => __('Tax', 'woocommerce') . " Tab",
			'shipping'    => __('Shipping', 'woocommerce') . " Tab",
			'checkout'    => _x('Payments', 'Settings tab label', 'woocommerce') . " Tab",
			'account'     => __('Accounts &amp; Privacy', 'woocommerce') . " Tab",
			'email'       => __('Emails', 'woocommerce') . " Tab",
			'integration' => __('Integration', 'woocommerce') . " Tab",
			'advanced'    => __('Advanced', 'woocommerce') . " Tab",
		);

		return $choices;
	});

	add_filter('acf/location/rule_operators/wc_settings_page', function($choices)
	{
		$choices = array
		(
			'==' => __('is equal to', 'acf'),
		);

		return $choices;
	}, 10, 3);
