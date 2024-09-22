<?php
wp_enqueue_script( 'jquery.cookie' );
stm_lms_register_script( 'account/v1/fast_login' );

$restrict_registration = STM_LMS_Options::get_option( 'restrict_registration', false );

wp_localize_script(
	'stm-lms-account/v1/fast_login',
	'stm_lms_fast_login',
	array(
		'translations'          => array(
			'sign_up' => esc_html__( 'Sign Up', 'masterstudy-lms-learning-management-system' ),
			'sign_in' => esc_html__( 'Sign In', 'masterstudy-lms-learning-management-system' ),
		),
		'restrict_registration' => $restrict_registration,
	)
);

stm_lms_register_style( 'account/v1/fast_login' );
wp_enqueue_style( 'masterstudy-button' );
?>

<div id="stm_lms_fast_login">
	<div class="stm_lms_fast_login">
		<div class="stm_lms_fast_login__head">
			<h3 v-html="translations.sign_up" v-if="!login && !restrict_registration"></h3>
			<h3 v-html="translations.sign_in" v-else></h3>
		</div>
		<div class="stm_lms_fast_login__body">
			<div class="stm_lms_fast_login__field" :class="{'stm_lms_fast_login__field_has-error': hasError('email')}">
				<input
					type="email"
					class="stm_lms_fast_login__input"
					v-model="email"
					placeholder="<?php echo esc_html__( 'Enter your email', 'masterstudy-lms-learning-management-system' ); ?>"
				>
				<span v-for="error in errors" :key="error.id" v-if="error.field === 'email'" :data-id="error.id" class="stm_lms_fast_login__error">
					{{ error.text }}
				</span>
			</div>
			<div class="stm_lms_fast_login__field" :class="{'stm_lms_fast_login__field_has-error': hasError('password')}">
				<input
					type="password"
					v-model="password"
					class="stm_lms_fast_login__input stm_lms_fast_login__input_pass"
					placeholder="<?php echo esc_html__( 'Enter your password', 'masterstudy-lms-learning-management-system' ); ?>"
				>
				<span v-for="error in errors" :key="error.id" v-if="error.field === 'password'" :data-id="error.id" class="stm_lms_fast_login__error">
					{{ error.text }}
				</span>
				<span @click.prevent="showPass($event)" class="stm_lms_fast_login__input-show-pass"></span>
			</div>
			<div class="stm_lms_fast_login__submit">
				<a
					v-if="!login && !restrict_registration"
					href="#"
					class="masterstudy-button masterstudy-button_style-primary masterstudy-button_size-sm"
					:class="{'masterstudy-button_loading' : loading}"
					@click.prevent="register()"
				>
					<span class="masterstudy-button__title">
						<?php echo esc_html__( 'Sign up', 'masterstudy-lms-learning-management-system' ); ?>
					</span>
				</a>
				<a
					v-else
					href="#"
					class="masterstudy-button masterstudy-button_style-primary masterstudy-button_size-sm"
					:class="{'masterstudy-button_loading' : loading}"
					@click.prevent="logIn()"
				>
					<span class="masterstudy-button__title">
						<?php echo esc_html__( 'Sign in', 'masterstudy-lms-learning-management-system' ); ?>
					</span>
				</a>
			</div>
		</div>
		<div class="stm_lms_fast_login__switch">
			<div class="stm_lms_fast_login__switch-account" v-if="!login && !restrict_registration">
				<span class="stm_lms_fast_login__switch-account-title">
					<?php echo esc_html__( 'Have account?', 'masterstudy-lms-learning-management-system' ); ?>
				</span>
				<a
					href="#"
					v-html="translations.sign_in"
					class="stm_lms_fast_login__switch-account-link"
					@click.prevent="changeForm(true)"
				>
				</a>
			</div>
			<div class="stm_lms_fast_login__switch-account" v-else-if="!restrict_registration">
				<span class="stm_lms_fast_login__switch-account-title">
					<?php esc_html_e( 'No account?', 'masterstudy-lms-learning-management-system' ); ?>
				</span>
				<a
					href="#"
					v-html="translations.sign_up"
					class="stm_lms_fast_login__switch-account-link"
					@click.prevent="changeForm(false)"
				>
				</a>
			</div>
		</div>
	</div>
</div>
