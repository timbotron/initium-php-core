<?php $this->layout('app::basic'); ?>
<div class="grd-row-col-2-6--md">

</div>
<div class="brdr--light-gray grd-row-col-2-6--md p1">
<h3>Admin settings</h3>

<form method="post" action="<?= SITE_URL ?>admin">
    <p>
        <label>
            <input type="checkbox" name="allow_signups" value="1" <?= $allow_signups ? 'checked' : '' ?>>
            Allow new sign-ups
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="require_valid_email" value="1" <?= $require_valid_email ? 'checked' : '' ?>>
            Require valid email
        </label>
        <br>
        <small>On: sign-ups are emailed a set-password link (needs Mailgun). Off: new users skip email and go straight to the set-password page &mdash; use this for installs without Mailgun.</small>
    </p>
    <button type="submit">Save</button>
</form>

</div>
