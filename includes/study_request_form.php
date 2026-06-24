<?php
/**
 * Formulario compartido de "Solicitar autorización de estudios".
 * Lo usan tanto la página pública (invitado) como la del portal (autenticado),
 * para no duplicar el markup ni la lógica.
 *
 *   render_study_request_form([
 *     'mode'     => 'guest' | 'portal',
 *     'prefill'  => ['full_name','cedula','phone','email','insurer','insurer_member_id'],
 *     'imaging'  => [ 'Sonografía', ... ],   // catálogo de imágenes/otros
 *     'lab'      => [ 'Laboratorio Clínico y Banco de Sangre', ... ],
 *     'insurers' => [ ['name'=>'ARS Humano'], ... ],
 *   ]);
 *
 * Requiere helpers.php cargado (e()).
 */

if (!function_exists('render_study_request_form')) {
    function render_study_request_form(array $opts): void
    {
        $mode     = ($opts['mode'] ?? 'guest') === 'portal' ? 'portal' : 'guest';
        $pf       = $opts['prefill']  ?? [];
        $imaging  = $opts['imaging']  ?? [];
        $lab      = $opts['lab']      ?? [];
        $insurers = $opts['insurers'] ?? [];
        $hcap     = (string) ($opts['hcaptcha_sitekey'] ?? '');
        $isGuest  = $mode === 'guest';
        ?>
        <form id="se-form" class="se-form" data-mode="<?= e($mode) ?>" novalidate>

            <!-- Paso 1: tipo de estudio -->
            <section class="portal-card se-section" data-se-step="tipo">
                <h2 class="portal-section-title"><span class="se-step-num">1</span> ¿Qué necesitas autorizar?</h2>
                <p class="se-help">Selecciona el tipo de estudio que te indicó tu médico.</p>
                <div class="se-type-grid" role="radiogroup" aria-label="Tipo de estudio">
                    <label class="se-type">
                        <input type="radio" name="study_type" value="imaging" required>
                        <span class="se-type-card">
                            <span class="se-type-ic"><i data-lucide="scan"></i></span>
                            <span class="se-type-t">Imágenes</span>
                            <span class="se-type-s">Radiografía, sonografía, resonancia, tomografía…</span>
                        </span>
                    </label>
                    <label class="se-type">
                        <input type="radio" name="study_type" value="lab">
                        <span class="se-type-card">
                            <span class="se-type-ic"><i data-lucide="flask-conical"></i></span>
                            <span class="se-type-t">Laboratorio</span>
                            <span class="se-type-s">Análisis de sangre, orina y otras pruebas.</span>
                        </span>
                    </label>
                    <label class="se-type">
                        <input type="radio" name="study_type" value="both">
                        <span class="se-type-card">
                            <span class="se-type-ic"><i data-lucide="layers"></i></span>
                            <span class="se-type-t">Ambos</span>
                            <span class="se-type-s">Imágenes y laboratorio en una misma solicitud.</span>
                        </span>
                    </label>
                </div>
                <p class="se-error" data-se-error="tipo" hidden></p>
            </section>

            <!-- Paso 2: estudios -->
            <section class="portal-card se-section" data-se-step="estudios" hidden>
                <h2 class="portal-section-title"><span class="se-step-num">2</span> ¿Cuáles estudios?</h2>
                <p class="se-help">Marca lo que aparece en tu orden médica. Usa el buscador; si no lo encuentras, escríbelo abajo.</p>

                <div class="se-search">
                    <i data-lucide="search"></i>
                    <input type="search" class="form-input" data-se-search autocomplete="off"
                        placeholder="Busca tu estudio (ej. sonografía abdominal, hemograma, tomografía…)">
                </div>

                <div class="se-catalog" data-se-catalog>
                    <div class="se-cat-loading" data-se-cat-loading><span class="se-spin"></span> Cargando estudios…</div>
                </div>

                <label class="form-label se-mt" for="se-other">Otros estudios (según tu orden)</label>
                <textarea id="se-other" name="other_studies" rows="2" class="form-input"
                    placeholder="Ej.: Sonografía abdominal, Perfil tiroideo…"></textarea>
                <p class="se-error" data-se-error="estudios" hidden></p>
            </section>

            <!-- Paso 3: seguro y referencia -->
            <section class="portal-card se-section" data-se-step="seguro" hidden>
                <h2 class="portal-section-title"><span class="se-step-num">3</span> Tu seguro</h2>
                <p class="se-help">Con esto el agente de seguros gestiona tu autorización y calcula tu copago.</p>
                <div class="portal-grid-2">
                    <div>
                        <label class="form-label" for="se-insurer">Aseguradora (ARS)</label>
                        <select id="se-insurer" name="insurer" class="form-input">
                            <option value="">— Selecciona tu ARS —</option>
                            <?php
                            $pfIns = (string)($pf['insurer'] ?? '');
                            $known = false;
                            foreach ($insurers as $ins):
                                $nm = is_array($ins) ? ($ins['name'] ?? '') : (string)$ins;
                                if ($nm === '') continue;
                                $sel = ($pfIns !== '' && $pfIns === $nm) ? ' selected' : '';
                                if ($sel) $known = true;
                                ?>
                                <option value="<?= e($nm) ?>"<?= $sel ?>><?= e($nm) ?></option>
                            <?php endforeach; ?>
                            <option value="__other__"<?= ($pfIns !== '' && !$known) ? ' selected' : '' ?>>Otra / no está en la lista</option>
                            <option value="__none__">Pagaré sin seguro</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="se-member">Nº de afiliado / contrato</label>
                        <input type="text" id="se-member" name="insurer_member_id" class="form-input"
                            value="<?= e((string)($pf['insurer_member_id'] ?? '')) ?>" placeholder="El que aparece en tu carnet">
                    </div>
                </div>
                <div class="portal-grid-2 se-mt" data-se-insurer-other<?= ($pfIns !== '' && !$known) ? '' : ' hidden' ?>>
                    <div>
                        <label class="form-label" for="se-insurer-name">Nombre de tu aseguradora</label>
                        <input type="text" id="se-insurer-name" name="insurer_other" class="form-input"
                            value="<?= e(($pfIns !== '' && !$known) ? $pfIns : '') ?>" placeholder="Escribe el nombre de tu ARS">
                    </div>
                    <div>
                        <label class="form-label" for="se-plan">Plan (opcional)</label>
                        <input type="text" id="se-plan" name="insurer_plan" class="form-input" placeholder="Ej.: Complementario">
                    </div>
                </div>

                <div class="portal-grid-2 se-mt">
                    <div>
                        <label class="form-label" for="se-center">¿De dónde te refieren? (opcional)</label>
                        <input type="text" id="se-center" name="referring_center" class="form-input"
                            placeholder="Ej.: otro centro de salud o tu médico privado">
                    </div>
                    <div>
                        <label class="form-label" for="se-rdoctor">Médico que indicó (opcional)</label>
                        <input type="text" id="se-rdoctor" name="referring_doctor" class="form-input" placeholder="Nombre del médico">
                    </div>
                </div>
            </section>

            <?php if ($isGuest): ?>
            <!-- Paso 4: tus datos (solo invitado) -->
            <section class="portal-card se-section" data-se-step="datos" hidden>
                <h2 class="portal-section-title"><span class="se-step-num">4</span> Tus datos</h2>
                <p class="se-help">Creamos tu acceso al portal para que sigas el estado y veas tu copago. Es gratis y toma segundos.</p>
                <div class="portal-grid-2">
                    <div>
                        <label class="form-label" for="se-name">Nombre completo</label>
                        <input type="text" id="se-name" name="full_name" class="form-input" required
                            value="<?= e((string)($pf['full_name'] ?? '')) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="se-cedula">Cédula</label>
                        <input type="text" id="se-cedula" name="cedula" class="form-input" required
                            value="<?= e((string)($pf['cedula'] ?? '')) ?>" placeholder="000-0000000-0">
                    </div>
                </div>
                <div class="portal-grid-2 se-mt">
                    <div>
                        <label class="form-label" for="se-phone">Teléfono / WhatsApp</label>
                        <input type="tel" id="se-phone" name="phone" class="form-input" required
                            value="<?= e((string)($pf['phone'] ?? '')) ?>" placeholder="(809) 000-0000">
                    </div>
                    <div>
                        <label class="form-label" for="se-email">Correo electrónico (opcional)</label>
                        <input type="email" id="se-email" name="email" class="form-input"
                            value="<?= e((string)($pf['email'] ?? '')) ?>" placeholder="nombre@correo.com">
                    </div>
                </div>
                <p class="se-error" data-se-error="datos" hidden></p>
            </section>
            <?php endif; ?>

            <!-- Paso 5: documentos -->
            <section class="portal-card se-section" data-se-step="docs" hidden>
                <h2 class="portal-section-title"><span class="se-step-num"><?= $isGuest ? '5' : '4' ?></span> Sube tus documentos</h2>
                <p class="se-help">Una foto clara basta. Aceptamos imágenes (JPG, PNG) o PDF, hasta 5&nbsp;MB cada uno.</p>

                <div class="se-files">
                    <div class="se-file" data-doc="order">
                        <div class="se-file-meta">
                            <span class="se-file-ic"><i data-lucide="file-text"></i></span>
                            <div><strong>Orden médica</strong><span class="se-tag se-tag-req">Obligatorio</span></div>
                        </div>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
                        <button type="button" class="btn btn-outline se-file-btn"><i data-lucide="upload"></i> Subir</button>
                        <div class="se-file-prev" hidden></div>
                    </div>

                    <div class="se-file" data-doc="insurance_front">
                        <div class="se-file-meta">
                            <span class="se-file-ic"><i data-lucide="credit-card"></i></span>
                            <div><strong>Carnet del seguro (frente)</strong><span class="se-tag se-tag-req">Obligatorio</span></div>
                        </div>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
                        <button type="button" class="btn btn-outline se-file-btn"><i data-lucide="upload"></i> Subir</button>
                        <div class="se-file-prev" hidden></div>
                    </div>

                    <div class="se-file" data-doc="insurance_back">
                        <div class="se-file-meta">
                            <span class="se-file-ic"><i data-lucide="credit-card"></i></span>
                            <div><strong>Carnet del seguro (dorso)</strong><span class="se-tag">Opcional</span></div>
                        </div>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
                        <button type="button" class="btn btn-outline se-file-btn"><i data-lucide="upload"></i> Subir</button>
                        <div class="se-file-prev" hidden></div>
                    </div>

                    <div class="se-file" data-doc="cedula">
                        <div class="se-file-meta">
                            <span class="se-file-ic"><i data-lucide="id-card"></i></span>
                            <div><strong>Cédula</strong><span class="se-tag">Opcional</span></div>
                        </div>
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
                        <button type="button" class="btn btn-outline se-file-btn"><i data-lucide="upload"></i> Subir</button>
                        <div class="se-file-prev" hidden></div>
                    </div>
                </div>
                <p class="se-error" data-se-error="docs" hidden></p>
            </section>

            <!-- Confirmar -->
            <section class="portal-card se-section" data-se-step="confirm" hidden>
                <label class="se-consent">
                    <input type="checkbox" name="consent_contact" value="1" required>
                    <span>Autorizo al hospital a contactarme por teléfono/WhatsApp para gestionar esta solicitud y acepto la
                        <a href="<?= e(base_url('politica-de-privacidad')) ?>" class="portal-text-link" target="_blank" rel="noopener">política de privacidad</a>.</span>
                </label>

                <div class="se-summary" data-se-summary></div>

                <?php if ($hcap !== ''): ?>
                    <div class="h-captcha se-mt" data-sitekey="<?= e($hcap) ?>"></div>
                <?php endif; ?>

                <p class="se-error" data-se-error="submit" hidden></p>

                <button type="submit" class="btn btn-green se-submit" id="se-submit">
                    <i data-lucide="send"></i> Enviar solicitud
                </button>
                <p class="se-note"><i data-lucide="shield-check"></i> Tu información viaja cifrada y solo la ve el equipo del hospital.</p>
            </section>

            <!-- Resultado (éxito) -->
            <section class="portal-card se-section se-done" data-se-step="done" hidden>
                <div class="se-done-ic"><i data-lucide="check-check"></i></div>
                <h2>¡Solicitud enviada!</h2>
                <p class="se-done-copy" data-se-done-copy></p>
                <div class="se-done-code" data-se-done-code hidden></div>
                <div class="se-done-actions" data-se-done-actions></div>
            </section>

            <div class="se-nav">
                <button type="button" class="btn btn-outline se-back" data-se-back hidden><i data-lucide="arrow-left"></i> Atrás</button>
                <button type="button" class="btn btn-green se-next" data-se-next>Continuar <i data-lucide="arrow-right"></i></button>
            </div>
        </form>
        <?php
    }
}
