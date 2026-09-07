// Homely — JS minimal : bascule clair/sombre, ouverture des modales,
// disparition auto des messages flash, préremplissage du formulaire de
// pièce depuis le catalogue suggéré. Toute la logique métier (statuts,
// dates, pourcentages) reste côté serveur (Twig affiche les valeurs
// calculées par src/Service/Dirtiness.php).

(function () {
    var toggle = document.getElementById('themeToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var root = document.documentElement;
            var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            root.setAttribute('data-theme', next);
            try {
                localStorage.setItem('homely-theme', next);
            } catch (e) {
                /* stockage indisponible : le thème reste valable pour la session */
            }
        });
    }
})();

(function () {
    document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dialog = document.getElementById(btn.getAttribute('data-open-modal'));
            if (dialog && typeof dialog.showModal === 'function') {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('dialog.sheet-dialog').forEach(function (dialog) {
        dialog.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                dialog.close();
            });
        });

        // Ferme la modale en cliquant sur le fond (backdrop).
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
})();

(function () {
    document.querySelectorAll('[data-flash]').forEach(function (flash) {
        setTimeout(function () {
            flash.classList.add('flash-hide');
        }, 3200);
    });
})();

(function () {
    // Menu déroulant maison (remplace le <select> natif : son popup ignore
    // notre thème sombre sur certains Chrome/Windows — fond blanc, texte
    // clair illisible — même avec color-scheme). Préremplit le nom et la
    // couleur de la pièce à la sélection — purement cosmétique, les champs
    // restent modifiables librement avant envoi.
    var wrapper = document.querySelector('[data-room-catalog]');
    if (!wrapper) {
        return;
    }

    var trigger = wrapper.querySelector('[data-custom-select-trigger]');
    var label = wrapper.querySelector('[data-custom-select-label]');
    var list = wrapper.querySelector('[data-custom-select-list]');
    var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
    var nameInput = document.getElementById('roomNameInput');
    var colorInput = document.getElementById('roomColorInput');

    function closeList() {
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    function openList() {
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    }

    function selectOption(option) {
        options.forEach(function (o) {
            o.setAttribute('aria-selected', o === option ? 'true' : 'false');
        });
        label.textContent = option.textContent;

        var value = option.getAttribute('data-value');
        if (!value) {
            return;
        }
        if (nameInput) {
            nameInput.value = value;
        }
        var color = option.getAttribute('data-color');
        if (colorInput && color) {
            colorInput.value = color;
        }
    }

    trigger.addEventListener('click', function () {
        if (list.hidden) {
            openList();
        } else {
            closeList();
        }
    });

    options.forEach(function (option) {
        option.addEventListener('click', function () {
            selectOption(option);
            closeList();
            trigger.focus();
        });
    });

    document.addEventListener('click', function (event) {
        if (!wrapper.contains(event.target)) {
            closeList();
        }
    });

    wrapper.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeList();
            trigger.focus();
            return;
        }
        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && !list.hidden) {
            event.preventDefault();
            var current = -1;
            options.forEach(function (o, index) {
                if (o.getAttribute('aria-selected') === 'true') {
                    current = index;
                }
            });
            var next = event.key === 'ArrowDown'
                ? Math.min(options.length - 1, current + 1)
                : Math.max(0, current - 1);
            selectOption(options[next]);
        }
    });

    // Repart de zéro à chaque fermeture de la modale de création.
    var dialog = wrapper.closest('dialog');
    if (dialog) {
        dialog.addEventListener('close', function () {
            closeList();
            selectOption(options[0]);
        });
    }
})();

(function () {
    // Paramètres : copie du code du foyer dans le presse-papiers.
    var copyBtn = document.getElementById('copyInviteCode');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var code = copyBtn.getAttribute('data-code') || '';
            var done = function () {
                var original = copyBtn.textContent;
                copyBtn.textContent = 'Copié !';
                setTimeout(function () {
                    copyBtn.textContent = original;
                }, 1600);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(done, done);
            } else {
                var helper = document.createElement('textarea');
                helper.value = code;
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                try {
                    document.execCommand('copy');
                } catch (e) {
                    /* copie indisponible : le code reste affiché à l'écran */
                }
                document.body.removeChild(helper);
                done();
            }
        });
    }
})();

(function () {
    // Paramètres : préremplit la modale d'édition de tâche depuis le bouton
    // crayon cliqué (nom, fréquence, dernière date), et branche le bouton
    // Supprimer sur le formulaire de suppression dédié.
    var editButtons = document.querySelectorAll('.settings-task-edit');
    if (editButtons.length === 0) {
        return;
    }

    var form = document.getElementById('taskEditForm');
    var deleteForm = document.getElementById('taskDeleteForm');
    var title = document.getElementById('taskEditTitle');
    var nameInput = document.getElementById('taskEditName');
    var frequencyInput = document.getElementById('taskEditFrequency');
    var lastDoneInput = document.getElementById('taskEditLastDone');
    var deleteBtn = document.getElementById('taskEditDelete');

    editButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-task-id');
            form.action = '/parametres/taches/' + id;
            deleteForm.action = '/parametres/taches/' + id + '/supprimer';
            title.textContent = 'Modifier « ' + btn.getAttribute('data-task-name') + ' »';
            nameInput.value = btn.getAttribute('data-task-name') || '';
            frequencyInput.value = btn.getAttribute('data-task-frequency') || '7';
            lastDoneInput.value = btn.getAttribute('data-task-last-done') || '';
        });
    });

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            var taskName = nameInput.value || 'cette tâche';
            if (window.confirm('Supprimer « ' + taskName + ' » ? Son historique de nettoyage sera aussi supprimé.')) {
                deleteForm.submit();
            }
        });
    }
})();
