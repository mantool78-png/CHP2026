<?php
/** Блок реквизитов: инструкция, QR, перевод по телефону, комментарий к платежу. */
$showPaymentComment = $showPaymentComment ?? true;
$paymentInstr = trim(payment_instructions());
?>
<?php if ($paymentInstr !== ''): ?>
    <p class="payment-instructions-text"><?= nl2br(h($paymentInstr)) ?></p>
<?php endif; ?>
<div class="payment-qr-wrap">
    <img src="/assets/qr_payment.png" alt="QR-код для перевода в Сбербанк или Т‑Банк" width="240" height="240" decoding="async" loading="lazy">
    <p class="muted payment-qr-hint">Только для приложений <strong>Сбербанка</strong> и <strong>Т‑Банка</strong>. С других банков&nbsp;&mdash; перевод по номеру телефона ниже.</p>
</div>
<?php $phonePay = payment_phone_transfer_block(); ?>
<?php if ($phonePay): ?>
    <div class="payment-phone-block">
        <p class="eyebrow">Перевод по номеру телефона</p>
        <p class="payment-phone-number">
            <a class="table-link" href="<?= h($phonePay['tel_href']) ?>"><?= h($phonePay['phone_display']) ?></a>
        </p>
        <p class="payment-phone-hint muted">СБП или перевод по номеру в приложении банка</p>
        <?php if ($phonePay['bank'] !== '' || $phonePay['recipient'] !== ''): ?>
            <dl class="payment-phone-details">
                <?php if ($phonePay['bank'] !== ''): ?>
                    <div>
                        <dt>Банк получателя</dt>
                        <dd><?= h($phonePay['bank']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($phonePay['recipient'] !== ''): ?>
                    <div>
                        <dt>Получатель</dt>
                        <dd><?= h($phonePay['recipient']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($showPaymentComment): ?>
<p class="payment-comment-hint"><span class="eyebrow">Комментарий к переводу</span><br><?= h(payment_comment_hint()) ?></p>
<?php endif; ?>
