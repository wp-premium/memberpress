<div class="mepr-deactivation-survey-popup" id="mepr-deactivation-survey">
  <div class="mepr-deactivation-survey-popup-content">

    <form class="mepr-deactivation-survey-form">

      <div class="mepr-deactivation-survey-popup-close"><i class="mp-icon mp-icon-cancel"></i></div>

      <div class="mepr-deactivation-survey-title"><i class="dashicons dashicons-testimonial"></i><?php esc_html_e('Quick Feedback', 'memberpress'); ?></div>

      <div class="mepr-deactivation-survey-description"><?php esc_html_e('If you have a moment, please share why you are deactivating MemberPress:', 'memberpress'); ?></div>

      <div class="mepr-deactivation-survey-options">
        <?php foreach ($options as $id => $option) : ?>
          <div class="mepr-deactivation-survey-option">
            <div class="mepr-deactivation-survey-option-input">
              <input type="radio" id="mepr-deactivation-survey-option-<?php echo esc_attr($plugin); ?>-<?php echo esc_attr($id); ?>" class="mepr-deactivation-survey-option-radio" name="mepr_deactivation_survey" value="<?php echo esc_attr($id); ?>">
              <label for="mepr-deactivation-survey-option-<?php echo esc_attr($plugin); ?>-<?php echo esc_attr($id); ?>" class="mepr-deactivation-survey-option-label">
                <?php echo esc_html($option['label']); ?>
              </label>
            </div>
            <?php if(!empty($option['details'])) : ?>
              <input type="text" class="mepr-deactivation-survey-option-details" placeholder="<?php echo esc_attr($option['details']); ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mepr-deactivation-survey-buttons">
        <button type="submit" class="button button-primary button-large"><?php esc_html_e('Submit & Deactivate', 'memberpress'); ?></button>
        <a class="mepr-deactivation-survey-button-skip"><?php esc_html_e('Skip & Deactivate', 'memberpress'); ?></a>
      </div>

    </form>

  </div>

  <div class="mepr-deactivation-survey-popup-overlay"></div>
</div>
