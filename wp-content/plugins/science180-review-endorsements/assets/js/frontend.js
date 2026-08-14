(function () {
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function setSelected(shell, id, title, cover) {
        var select = shell.querySelector('[data-s180re-book-select]');
        var titleNode = shell.querySelector('[data-s180re-selected-title]');
        var coverNode = shell.querySelector('[data-s180re-selected-cover]');

        shell.querySelectorAll('.s180re-book-choice').forEach(function (choice) {
            var input = choice.querySelector('input[type="radio"]');
            var isSelected = input && input.value === String(id);
            choice.classList.toggle('is-selected', isSelected);
            if (input) {
                input.checked = isSelected;
            }
        });

        if (select && select.value !== String(id)) {
            select.value = String(id);
        }

        if (titleNode) {
            titleNode.textContent = title || '';
        }

        if (coverNode && cover) {
            if (coverNode.tagName.toLowerCase() !== 'img') {
                var img = document.createElement('img');
                img.setAttribute('data-s180re-selected-cover', '');
                coverNode.replaceWith(img);
                coverNode = img;
            }
            coverNode.src = cover;
            coverNode.alt = title || '';
        }
    }

    ready(function () {
        document.querySelectorAll('[data-s180re-review]').forEach(function (shell) {
            shell.querySelectorAll('.s180re-book-choice input[type="radio"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    setSelected(shell, input.value, input.getAttribute('data-title'), input.getAttribute('data-cover'));
                });
            });

            var select = shell.querySelector('[data-s180re-book-select]');
            if (select) {
                select.addEventListener('change', function () {
                    var selected = select.options[select.selectedIndex];
                    setSelected(shell, select.value, selected.getAttribute('data-title') || selected.textContent, selected.getAttribute('data-cover'));
                });
            }
        });
    });
}());
