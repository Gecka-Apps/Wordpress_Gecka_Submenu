                <br style="clear: both" />
                
                <p class="edit-menu-item-autopopulate" >
                <label for="edit-menu-item-autopopulate-<?php echo $item_id; ?>">
					<input type="checkbox" id="edit-menu-item-autopopulate-<?php echo $item_id; ?>" class="edit-menu-item-autopopulate_type" name="menu-item-autopopulate_type[<?php echo $item_id; ?>]" value="subpages" <?php echo checked($item->autopopulate_type , 'subpages'); ?> />
					<?php _e( 'Automatically populate with child pages', Gecka_Submenu::Domain ); ?>
				</label>
                </p>
