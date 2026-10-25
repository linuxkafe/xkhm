/**
 * Wizard de importação – lógica client-side (vanilla JS, sem dependências além de jQuery).
 */
(function ($) {
    'use strict';

    const state = {
        currentStep: 'source',
        sourceData: {},
        schemaResult: null,
        jobId: null,
        progressTimer: null,
    };

    const i18n = xkhmImportWizard.i18n;
    const ajaxurl = xkhmImportWizard.ajaxurl;
    const nonce = xkhmImportWizard.nonce;

    function showStep(step) {
        $('.xkhm-step').removeClass('active');
        $(`.xkhm-step[data-step="${step}"]`).addClass('active');
        state.currentStep = step;
    }

    function ajax(action, data, callback) {
        $.post(ajaxurl, {
            action: 'xkhm_import_step',
            nonce: nonce,
            step: action,
            ...data,
        }).done(function (resp) {
            if (resp.success) {
                callback(resp);
            } else {
                alert(resp.error || 'Erro desconhecido');
            }
        }).fail(function () {
            alert('Erro de comunicação com o servidor');
        });
    }

    // --- Step 1: Origem ---
    $('input[name="source_type"]').on('change', function () {
        const val = $(this).val();
        $('#source-file').toggle(val === 'file');
        $('#source-remote').toggle(val === 'remote');
    });

    $('.xkhm-wizard-next[data-next="schema"]').on('click', function () {
        const type = $('input[name="source_type"]:checked').val();
        const data = { source_type: type };

        if (type === 'file') {
            const fileInput = $('input[name="sql_file"]')[0];
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                alert('Seleccione um ficheiro .sql ou .gz');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'xkhm_upload_sql');
            formData.append('nonce', nonce);
            formData.append('sql_file', fileInput.files[0]);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function (resp) {
                if (resp.success) {
                    ajax('validate_source', data, function (r) {
                        if (r.next_step === 'schema') {
                            showStep('schema');
                            ajax('validate_schema', {}, function (sr) {
                                $('#schema-result').html(sr.schema_html);
                                $('.xkhm-wizard-next[data-next="import"]').prop('disabled', !sr.can_continue);
                            });
                        }
                    });
                } else {
                    alert(resp.error || 'Erro no upload');
                }
            }).fail(function () {
                alert('Erro de comunicação com o servidor');
            });
            return;
        }

        if (type === 'remote') {
            data.remote_host = $('#remote_host').val();
            data.remote_port = $('#remote_port').val();
            data.remote_db   = $('#remote_db').val();
            data.remote_user = $('#remote_user').val();
            data.remote_pass = $('#remote_pass').val();
        }

        ajax('validate_source', data, function (resp) {
            if (resp.next_step === 'schema') {
                showStep('schema');
                ajax('validate_schema', {}, function (r) {
                    $('#schema-result').html(r.schema_html);
                    $('.xkhm-wizard-next[data-next="import"]').prop('disabled', !r.can_continue);
                });
            }
        });
    });

    // --- Step 2: Schema ---
    $('.xkhm-wizard-prev[data-prev="source"]').on('click', function () {
        showStep('source');
    });

    $('.xkhm-wizard-next[data-next="import"]').on('click', function () {
        showStep('import');
    });

    // --- Step 3: Import ---
    $('.xkhm-wizard-prev[data-prev="schema"]').on('click', function () {
        showStep('schema');
    });

    $('.xkhm-wizard-start-import').on('click', function () {
        $(this).prop('disabled', true).text(i18n.startImport + '…');
        ajax('start_import', {}, function (r) {
            state.jobId = r.job_id;
            pollProgress();
        });
    });

    function pollProgress() {
        if (state.progressTimer) clearInterval(state.progressTimer);
        state.progressTimer = setInterval(function () {
            ajax('import_progress', { job_id: state.jobId }, function (r) {
                const p = r.progress;
                const pct = p.total ? Math.round((p.done / p.total) * 100) : 0;
                $('#import-progress').val(pct);
                $('#import-status').text(`${pct}% – ${p.current_table} (${p.status})`);
                if (p.log && p.log.length) {
                    $('#import-log').html('<pre>' + p.log.slice(-20).join('\n') + '</pre>');
                }
                if (p.status === 'completed' || p.status === 'error') {
                    clearInterval(state.progressTimer);
                    if (p.status === 'completed') {
                        showStep('report');
                        renderReport(p);
                    } else {
                        alert('Erro na importação: ' + (p.log?.slice(-1)[0] || 'desconhecido'));
                    }
                }
            });
        }, 2000);
    }

    function renderReport(progress) {
        const tbody = $('#report-table tbody');
        tbody.empty();
        // O progress.log contém linhas por tabela; parse simples
        // Em produção o ImportService devolveria array estruturado.
        progress.log?.forEach(line => {
            if (line.includes('importada')) {
                const parts = line.match(/Tabela (\S+) importada \((\d+) linhas\)/);
                if (parts) {
                    tbody.append(`<tr><td>${parts[1]}</td><td>${parts[2]}</td><td>${parts[2]}</td><td>0</td><td>0</td></tr>`);
                }
            }
        });
    }

    // --- Step 4: Report ---
    $('#xkhm-wizard-finish').on('click', function () {
        window.location.reload();
    });

    // Navegação genérica prev/next
    $(document).on('click', '.xkhm-wizard-prev', function () {
        const prev = $(this).data('prev');
        if (prev) showStep(prev);
    });
    $(document).on('click', '.xkhm-wizard-next', function () {
        const next = $(this).data('next');
        if (next) showStep(next);
    });

})(jQuery);