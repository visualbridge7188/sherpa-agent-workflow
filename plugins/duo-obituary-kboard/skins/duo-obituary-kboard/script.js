(function(){
	'use strict';

	function formatDateForTitle(value){
		return value ? value.replace('T', ' ') : '';
	}

	window.duoObituaryBeforeSubmit = function(form){
		var deceased = form.querySelector('[name="kboard_option_deceased_name"]');
		var mourner = form.querySelector('[name="kboard_option_chief_mourner"]');
		var funeral = form.querySelector('[name="kboard_option_funeral_date"]');
		var title = form.querySelector('[name="title"]');
		var affiliation = form.querySelector('[name="kboard_option_affiliation"]');
		var death = form.querySelector('[name="kboard_option_death_date"]');

		if(!deceased.value.trim() || !mourner.value.trim() || !funeral.value.trim()){
			alert('필수 항목을 입력해 주세요.');
			return false;
		}

		var parts = [];
		if(affiliation && affiliation.value.trim()){
			parts.push(affiliation.value.trim());
		}
		parts.push(deceased.value.trim());
		if(death && death.value.trim()){
			parts.push(formatDateForTitle(death.value.trim()));
		}
		title.value = parts.join('/') || deceased.value.trim();
		return true;
	};

	function initLatestRolling(root){
		var targets = root.querySelectorAll('.is-rolling-target');
		if(!targets.length) return;

		targets.forEach(function(target){
			var tbody = target.querySelector('tbody');
			if(!tbody) return;

			// 기존 복제본 제거
			var originals = Array.from(tbody.querySelectorAll('tr:not(.duo-cloned)'));
			tbody.querySelectorAll('tr.duo-cloned').forEach(function(el){ el.remove(); });

			var visibleOriginals = originals.filter(function(tr){
				return tr.style.display !== 'none' && !tr.classList.contains('duo-obituary-no-results');
			});

			// 검색어가 있는 경우 롤링 강제 중단
			var searchInput = root.querySelector('.duo-obituary-latest-search-input');
			var isSearching = searchInput && searchInput.value.trim().length > 0;

			if(target._duoAnim) {
				target._duoAnim.cancel();
				target._duoAnim = null;
			}

			if(visibleOriginals.length <= 5 || isSearching){
				root.classList.remove('is-rolling');
				target.style.transform = 'translateY(0)';
				return;
			}

			root.classList.add('is-rolling');
			
			// 행 복제 (심리스 루프를 위해 하단에 붙임)
			visibleOriginals.forEach(function(tr){
				var clone = tr.cloneNode(true);
				clone.classList.add('duo-cloned');
				tbody.appendChild(clone);
			});

			// 동적 애니메이션 실행 (Web Animations API)
			var totalHeight = visibleOriginals.reduce(function(sum, tr){
				return sum + tr.getBoundingClientRect().height;
			}, 0);
			if(!totalHeight){
				totalHeight = visibleOriginals.length * 52;
			}
			var duration = visibleOriginals.length * 3000; // 항목당 3초

			target._duoAnim = target.animate([
				{ transform: 'translateY(0)' },
				{ transform: 'translateY(-' + totalHeight + 'px)' }
			], {
				duration: duration,
				iterations: Infinity,
				easing: 'linear'
			});

			// 호버 시 정지/재개 (각 target에 대해 개별 처리)
			target.parentElement.onmouseenter = function(){ if(target._duoAnim) target._duoAnim.pause(); };
			target.parentElement.onmouseleave = function(){ if(target._duoAnim) target._duoAnim.play(); };
		});
	}

	function initLatestSearch(root){
		var form = root.querySelector('.duo-obituary-latest-search-form');
		var input = root.querySelector('.duo-obituary-latest-search-input');
		var submit = root.querySelector('.duo-obituary-latest-search-submit');
		var overlay = root.querySelector('.duo-obituary-loading-overlay');
		if(!input) return;

		function doSearch(isInitial){
			var keyword = input.value.trim().toLowerCase();
			
			if(keyword.length > 0){
				root.classList.add('is-searching');
			} else {
				root.classList.remove('is-searching');
			}

			if(!isInitial) overlay.style.display = 'flex';

			var execute = function(){
				var tables = root.querySelectorAll('table');
				tables.forEach(function(table){
					var tbody = table.querySelector('tbody');
					if(!tbody) return; 

					var rows = Array.from(tbody.querySelectorAll('tr:not(.duo-cloned)')).filter(function(tr){
						return !tr.classList.contains('duo-obituary-empty-row') && !tr.classList.contains('duo-obituary-no-results');
					});

					var noResults = tbody.querySelector('.duo-obituary-no-results');
					var foundCount = 0;

					rows.forEach(function(row){
						if(!keyword){
							row.style.display = '';
							foundCount++;
						} else {
							var text = row.textContent.toLowerCase();
							if(text.indexOf(keyword) !== -1){
								row.style.display = '';
								foundCount++;
							} else {
								row.style.display = 'none';
							}
						}
					});

					if(noResults){
						noResults.style.display = foundCount === 0 ? '' : 'none';
					}
				});

				if(!isInitial) overlay.style.display = 'none';
				initLatestRolling(root);
			};

			if(isInitial){
				execute();
			} else {
				setTimeout(execute, 400);
			}
		}

		if(submit){
			submit.onclick = function(){ doSearch(false); };
		}
		input.onkeypress = function(e){
			if(e.which === 13 || e.keyCode === 13){
				doSearch(false);
				return false;
			}
		};

		// 무조건 초기 실행 (검색어가 없어도 롤링 초기화를 위해)
		doSearch(true);
	}


	document.addEventListener('DOMContentLoaded', function(){
		document.querySelectorAll('.duo-obituary-latest').forEach(function(root){
			initLatestSearch(root);
		});
	});

})();
