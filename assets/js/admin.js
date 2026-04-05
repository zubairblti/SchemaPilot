(function () {
	function createToastRegion() {
		var region = document.querySelector('.schemapilot-toast-region');
		if (region) {
			return region;
		}
		region = document.createElement('div');
		region.className = 'schemapilot-toast-region';
		document.body.appendChild(region);
		return region;
	}

	function showToast(message, type) {
		if (!message) {
			return;
		}

		var region = createToastRegion();
		var toast = document.createElement('div');
		toast.className = 'schemapilot-toast schemapilot-toast-' + (type || 'success');

		var text = document.createElement('span');
		text.textContent = message;
		toast.appendChild(text);

		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'schemapilot-toast-close';
		closeBtn.setAttribute('aria-label', 'Close');
		closeBtn.textContent = '\u00D7';
		closeBtn.addEventListener('click', function () {
			toast.remove();
		});
		toast.appendChild(closeBtn);

		region.appendChild(toast);

		setTimeout(function () {
			if (toast && toast.parentNode) {
				toast.remove();
			}
		}, 4000);
	}

	function ensureModal() {
		var backdrop = document.querySelector('.schemapilot-swal-backdrop');
		if (backdrop) {
			return backdrop;
		}

		backdrop = document.createElement('div');
		backdrop.className = 'schemapilot-swal-backdrop';

		var modal = document.createElement('div');
		modal.className = 'schemapilot-swal';

		var title = document.createElement('h3');
		title.className = 'schemapilot-swal-title';
		modal.appendChild(title);

		var message = document.createElement('p');
		message.className = 'schemapilot-swal-message';
		modal.appendChild(message);

		var actions = document.createElement('div');
		actions.className = 'schemapilot-swal-actions';

		var cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button';
		cancelBtn.textContent = 'Cancel';

		var confirmBtn = document.createElement('a');
		confirmBtn.className = 'button button-primary';
		confirmBtn.textContent = 'Delete';
		confirmBtn.href = '#';

		actions.appendChild(cancelBtn);
		actions.appendChild(confirmBtn);
		modal.appendChild(actions);
		backdrop.appendChild(modal);
		document.body.appendChild(backdrop);

		cancelBtn.addEventListener('click', function () {
			backdrop.classList.remove('is-visible');
			confirmBtn.href = '#';
		});

		backdrop.addEventListener('click', function (event) {
			if (event.target === backdrop) {
				backdrop.classList.remove('is-visible');
				confirmBtn.href = '#';
			}
		});

		return backdrop;
	}

	function initDeleteModals() {
		var links = document.querySelectorAll('.schemapilot-delete');
		if (!links.length) {
			return;
		}

		var backdrop = ensureModal();
		var titleEl = backdrop.querySelector('.schemapilot-swal-title');
		var messageEl = backdrop.querySelector('.schemapilot-swal-message');
		var confirmBtn = backdrop.querySelector('.button-primary');

		links.forEach(function (link) {
			link.addEventListener('click', function (event) {
				event.preventDefault();
				var title = link.getAttribute('data-title') || 'Delete schema?';
				var message = link.getAttribute('data-message') || 'This action cannot be undone.';
				titleEl.textContent = title;
				messageEl.textContent = message;
				confirmBtn.href = link.getAttribute('href');
				backdrop.classList.add('is-visible');
			});
		});
	}

	function initSchemaListTable() {
		var table = document.querySelector('.schemapilot-table');
		if (!table) {
			return;
		}

		var tbody = table.querySelector('tbody');
		if (!tbody) {
			return;
		}

		var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
		var dataRows = allRows.filter(function (row) {
			return !row.classList.contains('schemapilot-empty-row') && row.querySelectorAll('td').length > 1;
		});

		if (!dataRows.length) {
			return;
		}

		var searchInput = document.getElementById('schemapilot-search');
		var pageSizeSelect = document.getElementById('schemapilot-page-size');
		var pagination = document.getElementById('schemapilot-pagination');
		var emptyRow = null;
		var currentPage = 1;
		var pageSize = parseInt(pageSizeSelect ? pageSizeSelect.value : '10', 10) || 10;
		var searchTerm = '';

		function ensureEmptyRow() {
			if (emptyRow) {
				return emptyRow;
			}
			emptyRow = document.createElement('tr');
			emptyRow.className = 'schemapilot-empty-row';
			var cell = document.createElement('td');
			cell.colSpan = 5;
			cell.className = 'schemapilot-empty-cell';
			cell.textContent = 'No matching records.';
			emptyRow.appendChild(cell);
			return emptyRow;
		}

		function matchesSearch(row) {
			if (!searchTerm) {
				return true;
			}
			return row.textContent.toLowerCase().indexOf(searchTerm) !== -1;
		}

		function getFilteredRows() {
			return dataRows.filter(matchesSearch);
		}

		function clearPagination() {
			if (pagination) {
				pagination.innerHTML = '';
			}
		}

		function renderPagination(totalItems) {
			if (!pagination) {
				return;
			}

			clearPagination();

			var totalPages = Math.ceil(totalItems / pageSize);
			if (totalPages <= 1) {
				return;
			}

			var prevBtn = document.createElement('button');
			prevBtn.type = 'button';
			prevBtn.className = 'button schemapilot-page-btn';
			prevBtn.textContent = 'Previous';
			prevBtn.disabled = currentPage === 1;
			prevBtn.addEventListener('click', function () {
				if (currentPage > 1) {
					currentPage -= 1;
					updateTable();
				}
			});
			pagination.appendChild(prevBtn);

			for (var i = 1; i <= totalPages; i += 1) {
				var pageBtn = document.createElement('button');
				pageBtn.type = 'button';
				pageBtn.className = 'button schemapilot-page-btn';
				pageBtn.textContent = String(i);
				if (i === currentPage) {
					pageBtn.classList.add('is-active');
				}
				pageBtn.addEventListener('click', function (event) {
					var target = event.currentTarget;
					var page = parseInt(target.textContent, 10);
					if (!isNaN(page)) {
						currentPage = page;
						updateTable();
					}
				});
				pagination.appendChild(pageBtn);
			}

			var nextBtn = document.createElement('button');
			nextBtn.type = 'button';
			nextBtn.className = 'button schemapilot-page-btn';
			nextBtn.textContent = 'Next';
			nextBtn.disabled = currentPage === totalPages;
			nextBtn.addEventListener('click', function () {
				if (currentPage < totalPages) {
					currentPage += 1;
					updateTable();
				}
			});
			pagination.appendChild(nextBtn);
		}

		function updateTable() {
			var filtered = getFilteredRows();
			var totalItems = filtered.length;
			var totalPages = Math.ceil(totalItems / pageSize) || 1;

			if (currentPage > totalPages) {
				currentPage = totalPages;
			}

			var start = (currentPage - 1) * pageSize;
			var end = start + pageSize;

			dataRows.forEach(function (row) {
				row.style.display = 'none';
			});

			if (emptyRow && emptyRow.parentNode) {
				emptyRow.parentNode.removeChild(emptyRow);
			}

			if (!totalItems) {
				tbody.appendChild(ensureEmptyRow());
				clearPagination();
				return;
			}

			filtered.slice(start, end).forEach(function (row) {
				row.style.display = '';
			});

			renderPagination(totalItems);
		}

		if (searchInput) {
			searchInput.addEventListener('input', function (event) {
				searchTerm = event.target.value.toLowerCase().trim();
				currentPage = 1;
				updateTable();
			});
		}

		if (pageSizeSelect) {
			pageSizeSelect.addEventListener('change', function (event) {
				pageSize = parseInt(event.target.value, 10) || 10;
				currentPage = 1;
				updateTable();
			});
		}

		updateTable();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var toastData = document.querySelector('.schemapilot-toast-data');
		if (toastData) {
			showToast(toastData.getAttribute('data-message'), toastData.getAttribute('data-type'));
		}

		initDeleteModals();
		initSchemaListTable();
	});
})();
