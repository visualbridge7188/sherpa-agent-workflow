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
		if(!root.classList.contains('is-rolling')){
			return;
		}
		if(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches){
			return;
		}

		var wrap = root.querySelector('.duo-obituary-latest-table-wrap');
		var rows = root.querySelectorAll('tbody tr');
		if(!wrap || rows.length <= 5){
			return;
		}

		var index = 0;
		var paused = false;
		var interval = parseInt(root.getAttribute('data-interval'), 10) || 3000;

		root.addEventListener('mouseenter', function(){ paused = true; });
		root.addEventListener('mouseleave', function(){ paused = false; });
		root.addEventListener('focusin', function(){ paused = true; });
		root.addEventListener('focusout', function(){ paused = false; });

		window.setInterval(function(){
			if(paused){
				return;
			}
			var rowHeight = rows[0].getBoundingClientRect().height || 94;
			index += 1;
			if(index > rows.length - 5){
				index = 0;
			}
			wrap.scrollTo({
				top: index * rowHeight,
				behavior: 'smooth'
			});
		}, interval);
	}

	document.addEventListener('DOMContentLoaded', function(){
		document.querySelectorAll('.duo-obituary-latest').forEach(initLatestRolling);
	});
})();
