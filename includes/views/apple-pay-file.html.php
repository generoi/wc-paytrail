<tr valign="top" class="wc-paytrail-ap-ver-file">
	<th scope="row" class="titledesc">
		<label><?php esc_html_e( 'Verification File', 'wc-paytrail' ); ?></label>
	</th>
	<td class="forminp">
		<fieldset>
			<?php if ( ! is_wp_error( $status ) ) { ?>
				<mark class="yes">
					<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'File exists', 'wc-paytrail' ); ?> <a href="<?php echo esc_attr( $ap_ver_file_url ); ?>" class="ver-file-link" target="_blank"><span class="dashicons dashicons-external"></span></a>
				</mark>
			<?php } else { ?>
				<input type="hidden" name="wc_paytrail_ap_ver_file_generate" value="1" />

				<mark class="error">
					<span class="dashicons dashicons-warning"></span> <?php echo esc_html( $status->get_error_message() ); ?>
				</mark>

				<p class="description"><em><?php esc_html_e( 'Save settings to generate verification file', 'wc-paytrail' ); ?></em></p>
			<?php } ?>
		</fieldset>
	</td>
</tr>