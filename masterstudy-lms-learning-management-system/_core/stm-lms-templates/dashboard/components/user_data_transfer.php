<transition name="slide">
	<div class="user-data-transfer">
		<div class="user-data-transfer__action">
			<a href="#" class="user-data-transfer__btn user-data-transfer__btn-import" @click.prevent="modalVisible=true">
				<i class="fa fa-upload"></i>
				<span>
					<?php esc_html_e( 'Import CSV', 'masterstudy-lms-learning-management-system' ); ?>
				</span>
			</a>
			<a href="#" class="user-data-transfer__btn user-data-transfer__btn-export" @click.prevent="exportUsers">
				<i class="fa fa-download"></i>
				<span>
					<?php esc_html_e( 'Export CSV', 'masterstudy-lms-learning-management-system' ); ?>
				</span>
			</a>
		</div>
		<div class="user-data-transfer__import" >
			<div :class="{ 'user-data-transfer__modal': true, 'is-open': modalVisible }" ref="transferModal">
				<div class="user-data-transfer__modal-wrapper">
					<div class="user-data-transfer__modal-header">
						<span class="user-data-transfer__modal-title">
							<span v-if="importStep<3"><?php esc_html_e( 'Import students from CSV', 'masterstudy-lms-learning-management-system' ); ?></span>
							<span v-if="importStep==4"><?php esc_html_e( 'Import partially complete', 'masterstudy-lms-learning-management-system' ); ?></span>
						</span> 
						<span class="user-data-transfer__modal-close" @click="closeImportModal()"></span>
					</div>
					<div class="user-data-transfer__modal-text" v-if="importStep<3 || importStep==4">
						<span v-if="importStep<3">
							<?php esc_html_e( 'Invalid email addresses will not be imported.', 'masterstudy-lms-learning-management-system' ); ?>
						</span>
						<span v-if="importStep==4">
							{{importedUsers}} <?php esc_html_e( 'users imported.', 'masterstudy-lms-learning-management-system' ); ?>
						</span>
					</div>
					<div class="user-data-transfer__modal-download" v-if="importStep<2">
						<a href="<?php echo esc_url( STM_LMS_URL . 'assets/samples/import_users.csv' ); // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>" class="user-data-transfer__btn user-data-transfer__btn-download" download>
							<i class="fa fa-download"></i>
							<span>
								<?php esc_html_e( 'Download a CSV file template', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
						</a>
					</div>
					<div class="user-data-transfer__info" v-if="importStep==4">
						<span class="user-data-transfer__warning">
							<i class="fas fa-exclamation-triangle"></i>
							<?php esc_html_e( 'The users below were not imported as they had already been enrolled in this course.', 'masterstudy-lms-learning-management-system' ); ?>
						</span>
						<div  class="user-data-transfer__list">
							<span  class="user-data-transfer__list-item" v-for="(user, index) in beforeEnrolledUsers" :key="index">
								{{user.email}}
							</span>
						</div>
					</div>
					<div class="user-data-transfer-file-upload" v-if="importStep==0" ref="uploadFileDropArea">
						<div class="user-data-transfer-file-upload__item-wrapper"></div> 
						<div class="user-data-transfer-file-upload__field">
							<span class="user-data-transfer-file-upload__field-button" @click.prevent="uploadImportFile()">
								<?php esc_html_e( 'Upload CSV', 'masterstudy-lms-learning-management-system' ); ?>
							</span> 
							<div class="user-data-transfer-file-upload__field-text">
								<p><?php esc_html_e( 'Drag file here or click the button.', 'masterstudy-lms-learning-management-system' ); ?></p>
							</div> 
							<div :class="{ 'user-data-transfer-file-upload__field-error': true, 'is-visible': fileTypeError || emptyCsvFile }">
								<i class="fas fa-exclamation-triangle"></i>
								<span v-if="fileTypeError">
									<?php esc_html_e( 'Unsupported file type.', 'masterstudy-lms-learning-management-system' ); ?>
								</span>
								<span v-if="emptyCsvFile">
									<?php esc_html_e( 'CSV file is empty.', 'masterstudy-lms-learning-management-system' ); ?>
								</span>
								<a href="#" class="user-data-transfer__btn" @click.prevent="deleteAttachedFile()">
									<span>
										<?php esc_html_e( 'Try again', 'masterstudy-lms-learning-management-system' ); ?>
									</span>
								</a>
							</div> 
							<input ref="importFileInput" type="file" class="user-data-transfer-file-upload__input" accept=".csv">
						</div>
					</div>
					<div class="user-data-transfer__file-attachment" v-if="importStep==1">
						<div class="user-data-transfer__file-attachment__info">
							<img src="<?php echo esc_url( STM_LMS_URL . 'assets/icons/files/new/excel.svg' ); ?>" class="user-data-transfer__file-attachment__image">
							<div class="user-data-transfer__file-attachment__wrapper">
								<span class="user-data-transfer__file-attachment__title">
									{{userDataFileName}}
								</span>
								<span class="user-data-transfer__file-attachment__size">{{importFileSize}}</span>
								<span class="user-data-transfer__file-attachment__delete" @click="deleteAttachedFile()"></span>
							</div>
						</div>
					</div>
					<div class="user-data-transfer-progress" v-if="importStep==2">
						<div class="user-data-transfer-progress__bars">
							<span class="user-data-transfer-progress__bar-empty"></span>
							<span class="user-data-transfer-progress__bar-filled" :style="{width: importProgress + '%'}"></span>
						</div>
						<div class="user-data-transfer-progress__title">
							<span>
								<?php esc_html_e( 'Importing', 'masterstudy-lms-learning-management-system' ); ?> 
								{{userDataFileName}}:
							</span>
							<span class="user-data-transfer-progress__percent">{{importProgress}}%</span>
						</div>
					</div>
					<div class="user-data-transfer__message-box" v-if="importStep==3 || importStep==5">
						<div :class="{'user-data-transfer__message-box__icon-wrapper': true, 'error': importStep==5}">
							<span class="user-data-transfer__message-box__icon"></span>
						</div>
						<div class="user-data-transfer__message-box__message">
							<span class="user-data-transfer__message-box__title" v-if="importStep==3">
								<?php esc_html_e( 'Import successfully complete!', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
							<span class="user-data-transfer__message-box__description" v-if="importStep==3">
								{{importedUsers}} <?php esc_html_e( 'users were imported.', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
							<span class="user-data-transfer__message-box__title" v-if="importStep==5">
								<?php esc_html_e( 'Import has failed!', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
							<span class="user-data-transfer__message-box__description" v-if="importStep==5">
								<?php esc_html_e( 'Please check your CSV file and date and try again.', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
						</div>
						<div class="user-data-transfer__message-box__action">
							<button class="user-data-transfer__btn user-data-transfer__btn-action" @click="closeImportModal()">
								<span>
									<?php esc_html_e( 'Close', 'masterstudy-lms-learning-management-system' ); ?>
								</span>
							</button>
						</div>
					</div>
					<div class="user-data-transfer__modal-actions" v-if="importStep<2 || importStep==4">
						<button class="user-data-transfer__btn user-data-transfer__btn-action" :disabled="importStep==0" @click="importUsers" v-if="importStep<2">
							<span>
								<?php esc_html_e( 'Import', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
						</button>
						<button class="user-data-transfer__btn user-data-transfer__btn-action" v-if="importStep==4" @click="closeImportModal()">
							<span>
								<?php esc_html_e( 'Close', 'masterstudy-lms-learning-management-system' ); ?>
							</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</transition>
