<div class="wc-paytrail-invoice-status">
    <p>
        <strong><?php echo esc_html( sprintf( __('%s status:', 'wc-paytrail' ), $provider_title ) ); ?></strong><br>

        <?php if ( $status === 'captured' ) { ?>
            <span class="wc-paytrail-label wc-paytrail-label-ok"><?php esc_html_e( 'Captured', 'wc-paytrail' ); ?></span>
        <?php } else if ( $status === 'pending' ) { ?>
            <span class="wc-paytrail-label wc-paytrail-label-info"><?php esc_html_e( 'Pending capture', 'wc-paytrail' ); ?></span>

            <a
                href="#"
                data-url="<?php echo esc_url( $capture_url ); ?>"
                data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" 
                id="wc-paytrail-capture-order"
            >
                    <?php esc_html_e( 'Capture now &raquo;', 'wc-paytrail' ); ?>
            </a>
        <?php } else if ( $status === 'failed' ) { ?>
            <span class="wc-paytrail-label wc-paytrail-label-error"><?php esc_html_e( 'Failed', 'wc-paytrail' ); ?></span>
        <?php } else { ?>
            <span class="wc-paytrail-label"><?php esc_html_e( 'N/A', 'wc-paytrail' ); ?></span>
        <?php } ?>
    </p>
</div>