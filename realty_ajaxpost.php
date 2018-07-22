<?php

/**
 * Plugin Name: Ajax post from front
 * Plugin URI:
 * Description: Allows to add posts from front end
 * Author: Katya Leurdo
 * Version:
 * Author URI:
 */
class RealtyAjaxpost
{

	/**
	 * Plugin url
	 *
	 * @var $plugin_url
	 */
	private $plugin_url;

	/**
	 * Plugin path
	 *
	 * @var $plugin_path
	 */
	private $plugin_path;

	/**
	 * RealtyAjaxpost constructor.
	 */
	public function __construct() {
	}

	/**
	 * Plugin initialisation
	 */
	public function init() {
		$this->plugin_url  = WP_PLUGIN_URL . '/' . dirname( plugin_basename( __FILE__ ) );
		$this->plugin_path = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'widgets_init', array( $this, 'load_widget' ) );

		add_action( 'wp_ajax_nopriv_realty_add_post', array( $this, 'add_post' ) );
		add_action( 'wp_ajax_realty_add_post', array( $this, 'add_post' ) );
	}

	/**
	 * Add js script
	 */
	public function add_scripts() {
		wp_enqueue_script( 'realty_ajaxpost', $this->plugin_url . '/js/main.js', array( 'jquery' ) );
		wp_localize_script( 'realty_ajaxpost', 'realty_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Load textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'rlt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Register widget
	 */
	public function load_widget() {
		register_widget( 'RealtyAjaxpostWidget' );
	}

	/**
	 * Ajax posting handler
	 */
	public function add_post() {
		$results = '';

		$title   = sanitize_text_field( wp_unslash( $_POST['r_title'] ) );
		$content = sanitize_textarea_field( wp_unslash( $_POST['r_content'] ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'realty',
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'pending',
				'post_author'  => get_current_user_id(),
			)
		);

		if ( 0 != $post_id ) {
			// Add featured image to post.
			include_once ABSPATH . 'wp-admin/includes/image.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/media.php';
			$file_handler = 'r_image';
			$attach_id    = media_handle_upload( $file_handler, $post_id );
			update_post_meta( $post_id, '_thumbnail_id', $attach_id );

			// Add meta fields.
			$meta_array = array(
				'r_area'        => 'area',
				'r_living_area' => 'living_area',
				'r_address'     => 'address',
				'r_floor'       => 'floor',
				'r_price'       => 'price',
			);
			foreach ( $meta_array as $form_field => $acf_field ) {
				if ( $_POST[ $form_field ] ) {
					$text = sanitize_text_field( wp_unslash( $_POST[ $form_field ] ) );
					update_post_meta( $post_id, $acf_field, $text );
				}
			}

			// Add terms.
			wp_set_post_terms( $post_id, (int) $_POST['r_type'], 'realty_type' );
			wp_set_post_terms( $post_id, (int) $_POST['r_place'], 'realty_place' );

			$results = __( 'Объект добавлен. Он появится в списке объектов после проверки администратором.', 'rlt' );
		} else {
			$results = __( 'Произошла ошибка, попробуйте еще раз', 'rlt' );
		}
		// Return the String
		wp_die( $results );
	}
}

class RealtyAjaxpostWidget extends WP_Widget
{

	/**
	 * RealtyAjaxpostWidget constructor.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'   => 'realty_ajaxpostwidget',
			'description' => __( 'Форма для добавления объектов недвижимости', 'rlt' ),
		);
		parent::__construct( 'RealtyAjaxpostWidget', __( 'Realty Форма', 'rlt' ), $widget_ops );
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$defaults = array( 'title' => __( 'Добавить объект', 'rlt' ) );
		$instance = wp_parse_args( (array) $instance, $defaults );
		?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php echo __( 'Заголовок:', 'rlt' ); ?></label>
            <input id="<?php echo $this->get_field_id( 'title' ); ?>"
                   name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>"
                   class="widefat"/>
        </p>
		<?php
	}

	/**
	 * Processes widget options to be saved
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	/**
	 * Outputs content of widget
	 *
	 * @param array $args Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance The settings for the particular instance of the widget.
	 */
	public function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $before_widget;
		if ( $title ) {
			echo $before_title . $title . $after_title;
		}
		echo '<ul>';
		echo $this->post_form();
		echo '</ul>';
		echo $after_widget;
	}

    /**
     * Dispays form for front-end posting.
     */
    public function post_form() {
	    ?>
        <div id="realty_text">
            <form id="realty_form" action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="r_title"><?php echo __( 'Заголовок', 'rlt' ); ?></label>
                    <input type="text" class="form-control" id="r_title" name="r_title" required>
                </div>
                <div class="form-group">
                    <label for="r_content"><?php echo __( 'Описание', 'rlt' ); ?></label>
                    <textarea class="form-control" id="r_content" name="r_content" required></textarea>
                </div>
                <div class="form-group">
                    <label for="r_image"><?php echo __( 'Картинка', 'rlt' ); ?></label>
                    <input type="file" class="form-control-file" id="r_image" name="r_image"
                           accept="image/x-png,image/gif,image/jpeg" required>
                </div>
                <p><?php echo __( 'Тип объекта', 'rlt' ); ?></p>
                <div class="form-group">
				    <?php
				    $types = get_terms(
					    array(
						    'taxonomy'   => 'realty_type',
						    'hide_empty' => 0,

					    )
				    );
				    foreach ( $types as $type ) {
					    echo "<div class='form-check'>";
					    echo "<input type='checkbox' class='form-check-input' name='r_type' id='r_type' value='$type->term_id' />";
					    echo "<label class='form-check-label' for='r_type'>" . $type->name . "</label>";
					    echo "</div>";
				    }
				    ?>
                </div>
                <p><?php echo __( 'Город', 'rlt' ) ?></p>
                <div class="form-group">
				    <?php
				    $types = get_terms(
					    array(
						    'taxonomy'   => 'realty_place',
						    'hide_empty' => 0,

					    )
				    );
				    foreach ( $types as $type ) {
					    echo "<div class='form-check'>";
					    echo "<input type='checkbox' class='form-check-input' name='r_place' id='r_place' value='$type->term_id' />";
					    echo "<label class='form-check-label' for='r_place'>" . $type->name . "</label>";
					    echo "</div>";
				    }
				    ?>
                </div>
                <div class="form-group">
                    <label for="r_area"><?php echo __( 'Площадь', 'rlt' ); ?></label>
                    <input type="text" class="form-control" id="r_area" name="r_area">
                </div>
                <div class="form-group">
                    <label for="r_living_area"><?php echo __( 'Жилая площадь', 'rlt' ); ?></label>
                    <input type="text" class="form-control" id="r_living_area" name="r_living_area">
                </div>
                <div class="form-group">
                    <label for="r_address"><?php echo __( 'Адрес', 'rlt' ); ?></label>
                    <input type="text" class="form-control" id="r_address" name="r_address">
                </div>
                <div class="form-group">
                    <label for="r_floor"><?php echo __( 'Этаж', 'rlt' ); ?></label>
                    <input type="text" class="form-control" id="r_floor" name="r_floor">
                </div>
                <div class="form-group">
                    <label for="r_price"><?php echo __( 'Стоимость', 'rlt' ); ?></label>
                    <input type="text" class="form-control" id="r_price" name="r_price">
                </div>
                <button type="submit" class="btn btn-primary"><?php echo __( 'Отправить', 'rlt' ); ?></button>
            </form>

            <div id="realty_response" class="alert alert-info mt-3" role="alert" style="display: none;"></div>

        </div>
	    <?php
    }
}

$realty_ajaxpost = new RealtyAjaxpost;
$realty_ajaxpost->init();