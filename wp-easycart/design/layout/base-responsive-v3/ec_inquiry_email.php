<?php $inquiry_model_number = $product->model_number; $unit_price = 0; $options_price = 0; $grid_price_change = 0; $options_price_onetime = 0; $price_multiplier = 0;
function wp_easycart_inquiry_get_dimension_decimal( $value ) {
	if ( $value == '1/16' ) {
		return .0625;
	} else if ( $value == '1/8' ) {
		return .1250;
	} else if ( $value == '3/16' ) {
		return .1875;
	} else if ( $value == '1/4' ) {
		return .2500;
	} else if ( $value == '5/16' ) {
		return .3125;
	} else if ( $value == '3/8' ) {
		return .3750;
	} else if ( $value == '7/16' ) {
		return .4375;
	} else if ( $value == '1/2' ) {
		return .5000;
	} else if ( $value == '9/16' ) {
		return .5625;
	} else if ( $value == '5/8' ) {
		return .6250;
	} else if ( $value == '11/16' ) {
		return .6875;
	} else if ( $value == '3/4' ) {
		return .7500;
	} else if ( $value == '13/16' ) {
		return .8125;
	} else if ( $value == '7/8' ) {
		return .8750;
	} else if ( $value == '15/16' ) {
		return .9375;
	} else {
		return 0;
	}
}
if ( $option1 ) {
	$options_price += $option1->optionitem_price;
}
if ( $option2 ) {
	$options_price += $option2->optionitem_price;
}
if ( $option3 ) {
	$options_price += $option3->optionitem_price;
}
if ( $option4 ) {
	$options_price += $option4->optionitem_price;
}
if ( $option5 ) {
	$options_price += $option5->optionitem_price;
}
if ( $product->use_both_option_types || $product->use_advanced_optionset ) {
	foreach ( $option_vals as $advanced_option ) {
		$advanced_option_details = $GLOBALS['ec_options']->get_optionitem( $advanced_option['optionitem_id'] );
		$advanced_option_data = $GLOBALS['ec_options']->get_option( $advanced_option['option_id'] );
		if ( '' != $advanced_option['optionitem_model_number'] ) {
			$inquiry_model_number = $inquiry_model_number . get_option( 'ec_option_model_number_extension' ) . $advanced_option['optionitem_model_number'];
		}
		if ( 'grid' == $advanced_option_data->option_type ) {
			$grid_id = $advanced_option['option_id'];
			if ( 0 != $advanced_option_details->optionitem_price ) {
				$grid_price_change = $grid_price_change + ( $advanced_option_details->optionitem_price * $advanced_option['optionitem_value'] );
			} else if ( 0 != $advanced_option_details->optionitem_price_onetime ) {
				$grid_price_change = $grid_price_change + $advanced_option_details->optionitem_price_onetime;
			} else if ( $advanced_option_details->optionitem_price_override >= 0 ) {
				$grid_price_change = $grid_price_change + ( ( $advanced_option_details->optionitem_price_override - $product->price ) * $advanced_option['optionitem_value'] );
			} else if ( $advanced_option_details->optionitem_price_multiplier > 1 ) {
				$grid_price_change = $product->price * ( $advanced_option_details->optionitem_price_multiplier - 1 );
			}
		} else if ( 'number' == $advanced_option_data->option_type ) {
			if ( 0 != $advanced_option_details->optionitem_price ) {
				$options_price = $options_price + ( $advanced_option_details->optionitem_price * $advanced_option['optionitem_value'] );
			} else if ( 0 != $advanced_option_details->optionitem_price_onetime ) {
				$options_price_onetime = $options_price_onetime + $advanced_option_details->optionitem_price_onetime;
			} else if ( $advanced_option_details->optionitem_price_override >= 0 ) {
				$product->price = $advanced_option_details->optionitem_price_override;
			}
			if ( 0 != $advanced_option_details->optionitem_price_multiplier ) {
				if ( 0 == $price_multiplier ) {
					$price_multiplier = 1;
				}
				$price_multiplier = $price_multiplier * $advanced_option_details->optionitem_price_multiplier * $advanced_option['optionitem_value'];
			}
		} else if ( 'dimensions1' == $advanced_option_data->option_type || 'dimensions2' == $advanced_option_data->option_type ) {
			$dimensions = json_decode( $advanced_option['optionitem_value'] );
			if ( 2 == count( $dimensions ) ) { 
				if ( ! get_option( 'ec_option_enable_metric_unit_display' ) ) {
					$product->price = $product->price * ( ( $dimensions[0] / 12 ) * ( $dimensions[1] / 12 ) );
				} else {
					$product->price = $product->price * ( ( $dimensions[0] / 1000 ) * ( $dimensions[1] / 1000 ) );
				}
			} else if ( 4 == count( $dimensions ) ) { 
				if ( ! get_option( 'ec_option_enable_metric_unit_display' ) ) {
					$product->price = $product->price * ( ( ( intval( $dimensions[0] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[1] ) ) / 12 ) * ( ( intval( $dimensions[2] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[3] ) ) / 12 ) );
				} else {
					$product->price = $product->price * ( ( ( intval( $dimensions[0] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[1] ) ) / 1000 ) * ( ( intval( $dimensions[2] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[3] ) ) / 1000 ) );
				}
			}
		} else {
			if ( 0 != $advanced_option_details->optionitem_price ) {
				$options_price = $options_price + $advanced_option_details->optionitem_price;
			} else if ( 0 != $advanced_option_details->optionitem_price_onetime ) {
				$options_price_onetime = $options_price_onetime + $advanced_option_details->optionitem_price_onetime;
			} else if ( $advanced_option_details->optionitem_price_override >= 0 ) {
				$product->price = $advanced_option_details->optionitem_price_override;
			}
			if ( 0 != $advanced_option_details->optionitem_price_multiplier ) {
				if ( 0 == $price_multiplier ) {
					$price_multiplier = 1;
				}
				$price_multiplier = $price_multiplier * $advanced_option_details->optionitem_price_multiplier;
			}
			if ( $advanced_option_details->optionitem_price_per_character > 0 ) {
				$num_chars = strlen( preg_replace('/\s+/', '', $advanced_option['optionitem_value'] ) );
				$options_price = $options_price + ( $num_chars * $advanced_option_details->optionitem_price_per_character );
			}
			if ( 0 != $advanced_option_details->optionitem_weight ) {
				$options_weight = $options_weight + $advanced_option_details->optionitem_weight;
			} else if ( 0 != $advanced_option_details->optionitem_weight_onetime ) {
				$options_weight_onetime = $options_weight_onetime + $advanced_option_details->optionitem_weight_onetime;
			} else if ( $advanced_option_details->optionitem_weight_override >= 0 ) {
				$weight = $advanced_option_details->optionitem_weight_override;
			}
			if ( $advanced_option_details->optionitem_weight_multiplier > 1 ) {
				$weight_multiplier = $advanced_option_details->optionitem_weight_multiplier;
			}
		}
	}
}
$unit_price = $product->price + $options_price;
if ( $price_multiplier > 0 ) {
	$unit_price = $unit_price * $price_multiplier;
}
?><html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style type='text/css'>
	<!--
		.style20 {font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 12px; }
		.style22 {font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
		.ec_option_label{font-family: Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; }
		.ec_option_name{font-family: Arial, Helvetica, sans-serif; font-size:11px; }
	-->
	</style>
</head>
<body>
	<table width='539' border='0' align='center'>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<a href="<?php echo esc_url_raw( $store_page ); ?>" target="_blank"><img src="<?php echo esc_attr( $email_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( "name" ) ); ?>" style="max-height:250px; max-width:100%; height:auto;" /></a>
			</td>
		</tr>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<h3><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_title' ); ?></h3>
			</td>
		</tr>
		<tr>
			<td colspan='4' align='left' class='style22'>
				&nbsp;&nbsp;&nbsp;
			</td>
		</tr>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<span class='style22'><?php echo esc_attr( $product->title ); ?> (<?php echo esc_attr( $product->model_number ); ?>)</span>
			</td>
		</tr>
		<?php if ( ! get_option( 'ec_option_hide_price_inquiry' ) ){ ?>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<span class='style22'><?php
					echo esc_attr( $GLOBALS['currency']->get_currency_display( $unit_price ) );
				?></span>
			</td>
		</tr>
		<?php }?>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<span class='style22'><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_name' ); ?> <?php echo esc_attr( stripslashes( $inquiry_name ) ); ?></span>
			</td>
		</tr>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<span class='style22'><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_email' ); ?> <?php echo esc_attr( stripslashes( $inquiry_email ) ); ?></span>
			</td>
		</tr>
		<?php if ( $product->use_both_option_types || ! $product->use_advanced_optionset ) {
			if ( $option1 ) {
				echo "<tr><td>";
				if ( $option1_option ) {
					echo "<span class=\"ec_option_label\">" . wp_easycart_escape_html( $option1_option->option_label ) . ": </span>";
				}
				echo "<span class=\"ec_option_name\">" . wp_easycart_escape_html( $option1->optionitem_name ) . "</span></td></tr>";
			}
			if ( $option2 ) {
				echo "<tr><td>";
				if ( $option2_option ) {
					echo "<span class=\"ec_option_label\">" . wp_easycart_escape_html( $option2_option->option_label ) . ": </span>";
				}
				echo "<span class=\"ec_option_name\">" . wp_easycart_escape_html( $option2->optionitem_name ) . "</span></td></tr>";
			}
			if ( $option3 ) {
				echo "<tr><td>";
				if ( $option3_option ) {
					echo "<span class=\"ec_option_label\">" . wp_easycart_escape_html( $option3_option->option_label ) . ": </span>";
				}
				echo "<span class=\"ec_option_name\">" . wp_easycart_escape_html( $option3->optionitem_name ) . "</span></td></tr>";
			}
			if ( $option4 ) {
				echo "<tr><td>";
				if ( $option4_option ) {
					echo "<span class=\"ec_option_label\">" . wp_easycart_escape_html( $option4_option->option_label ) . ": </span>";
				}
				echo "<span class=\"ec_option_name\">" . wp_easycart_escape_html( $option4->optionitem_name ) . "</span></td></tr>";
			}
			if ( $option5 ) {
				echo "<tr><td>";
				if ( $option5_option ) {
					echo "<span class=\"ec_option_label\">" . wp_easycart_escape_html( $option5_option->option_label ) . ": </span>";
				}
				echo "<span class=\"ec_option_name\">" . wp_easycart_escape_html( $option5->optionitem_name ) . "</span></td></tr>";
			}
		}

		if ( $product->use_both_option_types || $product->use_advanced_optionset ) {
			foreach ( $option_vals as $advanced_option ) {
				if ( $advanced_option['option_type'] == "file" ) {
					echo "<tr><td><span class=\"ec_option_label\">" . esc_attr( $advanced_option['option_label'] ) . ":</span> <span class=\"ec_option_name\"><a href=\"" . esc_attr( plugins_url( "/wp-easycart-data/products/uploads/" . $file_temp_num . "/" . $advanced_option['optionitem_value'], EC_PLUGIN_DATA_DIRECTORY ) ) . "\">" . esc_attr( $advanced_option['optionitem_value'] ) . "</a></span></td></tr>";
				} else if ( $advanced_option['option_type'] == "grid" ) {
					echo "<tr><td><span class=\"ec_option_label\">" . esc_attr( $advanced_option['option_label'] ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( $advanced_option['optionitem_name'] . " (" . $advanced_option['optionitem_value'] ) . ")" . "</span></td></tr>";
				} else {
					echo "<tr><td><span class=\"ec_option_label\">" . esc_attr( $advanced_option['option_label'] ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( htmlspecialchars( $advanced_option['optionitem_value'], ENT_QUOTES ) ) . "</span></td></tr>";
				}
			}
		} ?>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<span class='style22'><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_message' ); ?> <?php echo esc_attr( nl2br( stripslashes( $inquiry_message ) ) ); ?></span>
			</td>
		</tr>
		<tr>
			<td colspan='4' align='left' class='style22'>
				&nbsp;&nbsp;&nbsp;
			</td>
		</tr>
		<tr>
			<td colspan='4' align='left' class='style22'>
				<span class='style22'><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_thank_you' ); ?></span>
			</td>
		</tr>
	</table>
</body>
</html>