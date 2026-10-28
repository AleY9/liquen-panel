/* Liquen Panel — comportamiento del panel (sin dependencias). */
(function () {
	'use strict';
	var LP = window.LP || {};
	var form = document.querySelector('form.lp-form');
	var subiendo = 0;
	var sucio = false;
	var enviando = false;

	// ---------- Imágenes: se achican en el celular y se suben apenas se eligen ----------

	function achicar(file) {
		return new Promise(function (ok) {
			var tipo = (file.type || '').toLowerCase();
			var max = LP.maxPx || 2048;
			if (!window.createImageBitmap || !/^image\/(jpeg|png|webp|heic|heif)$/.test(tipo)) { ok(file); return; }
			createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
				var w = bmp.width, h = bmp.height, k = Math.min(1, max / Math.max(w, h));
				if (k === 1 && file.size < 900 * 1024 && tipo !== 'image/heic' && tipo !== 'image/heif') { ok(file); return; }
				var c = document.createElement('canvas');
				c.width = Math.round(w * k); c.height = Math.round(h * k);
				var ctx = c.getContext('2d');
				if (tipo === 'image/png') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); }
				ctx.drawImage(bmp, 0, 0, c.width, c.height);
				c.toBlob(function (b) {
					ok(b && (b.size < file.size || tipo === 'image/heic' || tipo === 'image/heif') ? new File([b], (file.name || 'imagen').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
				}, 'image/jpeg', 0.85);
			}).catch(function () { ok(file); });
		});
	}

	function subir(archivo, progreso) {
		return new Promise(function (ok) {
			var x = new XMLHttpRequest();
			x.open('POST', LP.ajax);
			if (x.upload && progreso) {
				x.upload.onprogress = function (e) { if (e.lengthComputable) { progreso(Math.round(e.loaded / e.total * 100)); } };
			}
			x.onload = function () {
				var r;
				try { r = JSON.parse(x.responseText); } catch (e) { r = { ok: false, msg: 'El servidor no respondió bien. Probá de nuevo.' }; }
				ok(r);
			};
			x.onerror = function () { ok({ ok: false, msg: 'Se cortó la conexión. Probá de nuevo cuando tengas mejor señal.' }); };
			var d = new FormData();
			d.append('action', 'lp_subir');
			d.append('nonce', LP.nonce);
			d.append('archivo', archivo, archivo.name || 'imagen.jpg');
			x.send(d);
		});
	}

	function prepararSlot(slot) {
		if (slot.dataset.listo) { return; }
		slot.dataset.listo = '1';
		var input = slot.querySelector('input[type=file]');
		var oculto = slot.querySelector('input[type=hidden]');
		var caja = slot.querySelector('.lp-slot-img');
		var estado = slot.querySelector('.lp-slot-estado');
		var txt = slot.querySelector('.lp-slot-btns label span');
		var quitar = slot.querySelector('.lp-quitar');
		var err = slot.querySelector('.lp-slot-error');

		function mostrar(url) {
			var v = caja.querySelector('img, .lp-slot-vacio');
			if (v) { v.remove(); }
			var n;
			if (url) { n = document.createElement('img'); n.src = url; n.alt = ''; }
			else { n = document.createElement('span'); n.className = 'lp-slot-vacio'; n.textContent = 'Sin imagen'; }
			caja.insertBefore(n, estado);
			txt.textContent = url ? 'Cambiar' : (slot.closest('.lp-galeria') ? 'Subir' : 'Subir imagen');
			quitar.hidden = !url;
			slot.classList.toggle('lp-slot-lleno', !!url);
		}
		function ocupado(si, m) { slot.classList.toggle('lp-ocupado', si); estado.hidden = !si; estado.textContent = m || ''; }

		input.addEventListener('change', function () {
			var f = input.files && input.files[0];
			input.value = '';
			if (!f) { return; }
			err.hidden = true;
			subiendo++;
			ocupado(true, 'Preparando…');
			achicar(f).then(function (a) {
				return subir(a, function (p) { estado.textContent = p < 100 ? 'Subiendo… ' + p + ' %' : 'Guardando…'; });
			}).then(function (r) {
				subiendo--;
				ocupado(false);
				if (!r.ok) { err.textContent = r.msg; err.hidden = false; return; }
				oculto.value = r.id;
				mostrar(r.url);
				sucio = true;
				galeriaAgregarHueco(slot);
			});
		});
		var elegir = slot.querySelector('.lp-elegir');
		if (elegir) {
			elegir.addEventListener('click', function () {
				err.hidden = true;
				abrirBiblioteca(function (r) {
					oculto.value = r.id;
					mostrar(r.url);
					sucio = true;
					galeriaAgregarHueco(slot);
				});
			});
		}
		quitar.addEventListener('click', function () {
			if (!quitar.classList.contains('lp-seguro')) {
				quitar.classList.add('lp-seguro');
				quitar.textContent = '¿Seguro?';
				setTimeout(function () { quitar.classList.remove('lp-seguro'); quitar.textContent = 'Quitar'; }, 4000);
				return;
			}
			quitar.classList.remove('lp-seguro');
			quitar.textContent = 'Quitar';
			oculto.value = '';
			mostrar('');
			sucio = true;
		});
	}

	// ---------- Biblioteca de medios: ventana con buscador ----------

	var bib = null;
	function abrirBiblioteca(alElegir) {
		if (!bib) { bib = crearBiblioteca(); }
		bib.alElegir = alElegir;
		bib.anterior = document.activeElement;
		bib.el.hidden = false;
		document.documentElement.classList.add('lp-modal-abierto');
		if (!bib.cargado) { bib.cargar(true); }
		setTimeout(function () { bib.buscar.focus(); }, 50);
	}
	function crearBiblioteca() {
		var el = document.createElement('div');
		el.className = 'lp-bib';
		el.hidden = true;
		el.setAttribute('role', 'dialog');
		el.setAttribute('aria-modal', 'true');
		el.setAttribute('aria-label', 'Elegir de la biblioteca');
		el.innerHTML = '<div class="lp-bib-caja">' +
			'<div class="lp-bib-cab"><strong>Elegir de la biblioteca</strong><button type="button" class="lp-mini lp-bib-cerrar">Cerrar</button></div>' +
			'<input type="search" class="lp-buscar lp-bib-buscar" placeholder="Buscar por nombre" aria-label="Buscar por nombre" autocomplete="off">' +
			'<div class="lp-bib-grilla" role="list"></div>' +
			'<p class="lp-vacio lp-bib-msg" hidden></p>' +
			'<button type="button" class="lp-btn lp-btn-sec lp-bib-mas" hidden>Cargar más</button>' +
			'</div>';
		document.body.appendChild(el);
		var b = {
			el: el,
			grilla: el.querySelector('.lp-bib-grilla'),
			msg: el.querySelector('.lp-bib-msg'),
			mas: el.querySelector('.lp-bib-mas'),
			buscar: el.querySelector('.lp-bib-buscar'),
			pag: 1, cargado: false, pidiendo: 0, alElegir: null, anterior: null
		};
		b.cerrar = function () {
			el.hidden = true;
			document.documentElement.classList.remove('lp-modal-abierto');
			if (b.anterior && b.anterior.focus) { b.anterior.focus(); }
		};
		b.cargar = function (desdeCero) {
			if (desdeCero) { b.pag = 1; b.grilla.innerHTML = ''; }
			var pedido = ++b.pidiendo;
			b.msg.hidden = false; b.msg.textContent = 'Cargando…'; b.mas.hidden = true;
			var u = LP.ajax + '?action=lp_biblioteca&nonce=' + encodeURIComponent(LP.nonceBib) + '&pag=' + b.pag + '&q=' + encodeURIComponent(b.buscar.value.trim());
			fetch(u, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
				if (pedido !== b.pidiendo) { return; }
				b.cargado = true;
				if (!r.ok) { b.msg.textContent = r.msg || 'No se pudo abrir la biblioteca.'; return; }
				r.items.forEach(function (it) {
					var x = document.createElement('button');
					x.type = 'button';
					x.className = 'lp-bib-item';
					x.setAttribute('role', 'listitem');
					x.setAttribute('aria-label', it.texto || 'Imagen');
					var i = document.createElement('img');
					i.src = it.mini; i.alt = ''; i.loading = 'lazy';
					x.appendChild(i);
					x.addEventListener('click', function () { b.cerrar(); if (b.alElegir) { b.alElegir(it); } });
					b.grilla.appendChild(x);
				});
				var vacio = !b.grilla.children.length;
				b.msg.hidden = !vacio;
				b.msg.textContent = vacio ? (b.buscar.value.trim() ? 'No hay imágenes con «' + b.buscar.value.trim() + '».' : 'Todavía no hay imágenes en la biblioteca.') : '';
				b.mas.hidden = !r.mas;
			}).catch(function () {
				if (pedido === b.pidiendo) { b.msg.hidden = false; b.msg.textContent = 'Se cortó la conexión. Probá de nuevo.'; }
			});
		};
		var t;
		b.buscar.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { b.cargar(true); }, 350); });
		b.mas.addEventListener('click', function () { b.pag++; b.cargar(false); });
		el.querySelector('.lp-bib-cerrar').addEventListener('click', b.cerrar);
		el.addEventListener('click', function (e) { if (e.target === el) { b.cerrar(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !el.hidden) { b.cerrar(); } });
		return b;
	}

	// Galería: siempre una casilla libre al final, hasta el máximo.
	function galeriaAgregarHueco(slot) {
		var g = slot.closest('.lp-galeria');
		if (!g) { return; }
		var slots = g.querySelectorAll('.lp-slot');
		var libres = [].filter.call(slots, function (s) { return !s.querySelector('input[type=hidden]').value; });
		if (libres.length || slots.length >= parseInt(g.dataset.max, 10)) { return; }
		var nuevo = slots[slots.length - 1].cloneNode(true);
		delete nuevo.dataset.listo;
		nuevo.classList.remove('lp-slot-lleno', 'lp-ocupado');
		nuevo.querySelector('input[type=hidden]').value = '';
		var img = nuevo.querySelector('.lp-slot-img img');
		if (img) { var s = document.createElement('span'); s.className = 'lp-slot-vacio'; s.textContent = 'Sin imagen'; img.replaceWith(s); }
		nuevo.querySelector('.lp-slot-btns label span').textContent = 'Subir';
		nuevo.querySelector('.lp-quitar').hidden = true;
		nuevo.querySelector('.lp-slot-error').hidden = true;
		g.appendChild(nuevo);
		prepararSlot(nuevo);
	}

	document.querySelectorAll('.lp-slot').forEach(prepararSlot);

	// ---------- Formulario ----------

	if (form) {
		form.addEventListener('input', function () { sucio = true; });
		form.addEventListener('change', function () { sucio = true; });

		// Contador de caracteres en textos con límite.
		form.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(function (el) {
			var max = parseInt(el.getAttribute('maxlength'), 10);
			if (!max || max < 60) { return; }
			var c = document.createElement('div');
			c.className = 'lp-contador';
			(el.closest('.lp-con-extremos') || el).insertAdjacentElement('afterend', c);
			function act() { var n = el.value.length; c.textContent = n + ' / ' + max; c.classList.toggle('lp-casi', n >= max * 0.9); }
			el.addEventListener('input', act); act();
		});

		// Fecha de fin: no antes que la de inicio.
		form.querySelectorAll('input[type=date][data-desde]').forEach(function (fin) {
			var ini = document.getElementById(fin.dataset.desde);
			if (!ini) { return; }
			function act() { fin.min = ini.value || ''; }
			ini.addEventListener('change', function () { act(); if (fin.value && ini.value && fin.value < ini.value) { fin.value = ini.value; } });
			act();
		});

		// «Quitar fecha».
		form.querySelectorAll('[data-limpiar]').forEach(function (b) {
			b.addEventListener('click', function () {
				var el = document.getElementById(b.dataset.limpiar);
				if (el) { el.value = ''; sucio = true; el.dispatchEvent(new Event('change', { bubbles: true })); }
				b.remove();
			});
		});

		form.addEventListener('submit', function (e) {
			if (subiendo > 0) {
				e.preventDefault();
				alert('Esperá a que terminen de subir las imágenes.');
				return;
			}
			enviando = true;
			setTimeout(function () {
				form.querySelectorAll('.lp-botonera button').forEach(function (b) { b.disabled = true; });
			}, 0);
		});

		window.addEventListener('beforeunload', function (e) {
			if ((sucio || subiendo) && !enviando) { e.preventDefault(); e.returnValue = ''; }
		});

		// ---------- Lógica condicional de ACF (mostrar / ocultar campos) ----------
		var condicionales = form.querySelectorAll('[data-lp-cond]');
		if (condicionales.length) {
			var valorDe = function (key) {
				var els = form.querySelectorAll('[name="lp[' + key + ']"], [name="lp[' + key + '][]"]');
				if (!els.length) { return null; }
				var multi = false, out = [], uno = '';
				els.forEach(function (el) {
					if (el.name.slice(-2) === '[]') {
						multi = true;
						if ((el.type === 'checkbox' || el.type === 'radio') ? el.checked : el.value) { out.push(el.value); }
					} else if (el.type === 'radio') {
						if (el.checked) { uno = el.value; }
					} else if (el.type === 'checkbox') {
						uno = el.checked ? '1' : (uno || '0');
					} else if (el.type !== 'hidden' || els.length === 1) {
						uno = el.value;
					}
				});
				return multi ? out : uno;
			};
			var regla = function (op, v, e) {
				var arr = Array.isArray(v), txt = arr ? v.join(' ') : String(v);
				switch (op) {
					case '==': return arr ? v.indexOf(e) !== -1 : v === e;
					case '!=': return arr ? v.indexOf(e) === -1 : v !== e;
					case '==empty': return arr ? !v.length : v === '';
					case '!=empty': return arr ? !!v.length : v !== '';
					case '==contains': return txt.toLowerCase().indexOf(String(e).toLowerCase()) !== -1;
					case '==pattern': try { return new RegExp(e).test(txt); } catch (x) { return true; }
					case '>': return !arr && v !== '' && !isNaN(v) && parseFloat(v) > parseFloat(e);
					case '<': return !arr && v !== '' && !isNaN(v) && parseFloat(v) < parseFloat(e);
				}
				return true;
			};
			var evaluar = function () {
				condicionales.forEach(function (c) {
					var grupos;
					try { grupos = JSON.parse(c.dataset.lpCond); } catch (x) { return; }
					var ver = grupos.some(function (g) {
						return g.every(function (r) {
							var v = Object.prototype.hasOwnProperty.call(r, 'fijo') ? r.fijo : valorDe(r.f);
							if (v === null) { v = ''; }
							return regla(r.op, v, r.v);
						});
					});
					c.hidden = !ver;
				});
			};
			form.addEventListener('change', evaluar);
			form.addEventListener('input', evaluar);
			evaluar();
		}

		// Si hay errores, ir al primero.
		var primero = document.querySelector('.lp-con-error');
		if (primero) { primero.scrollIntoView({ block: 'center' }); }
	}

	// Botones con doble toque para confirmar (Borrar, Pasar a borrador).
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('button[data-confirma]');
		if (!b || b.classList.contains('lp-seguro')) { return; }
		e.preventDefault();
		b.dataset.txt = b.textContent;
		b.textContent = b.dataset.confirma;
		b.classList.add('lp-seguro');
		setTimeout(function () { b.classList.remove('lp-seguro'); b.textContent = b.dataset.txt; }, 4000);
	});

	// ---------- Lista: «Ver todas» y buscador ----------

	document.querySelectorAll('.lp-ver-todas').forEach(function (b) {
		b.addEventListener('click', function () {
			b.parentNode.querySelectorAll('.lp-mas').forEach(function (it) { it.hidden = false; it.classList.remove('lp-mas'); });
			b.remove();
		});
	});

	var buscar = document.querySelector('.lp-buscar');
	var listas = document.querySelector('.lp-listas');
	if (buscar && listas) {
		var norm = function (t) { return (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); };
		var nada = listas.querySelector('.lp-sin-resultados');
		var antes = '';
		buscar.addEventListener('input', function () {
			var q = norm(buscar.value);
			var hay = 0;
			// Al empezar a buscar se guarda qué grupos estaban abiertos, para volver así al borrar.
			if (q && !antes) { listas.querySelectorAll('details.lp-grupo').forEach(function (d) { d.dataset.abierto = d.open ? '1' : ''; }); }
			antes = q;
			listas.classList.toggle('lp-buscando', !!q);
			listas.querySelectorAll('.lp-grupo').forEach(function (g) {
				var n = 0;
				g.querySelectorAll('.lp-item').forEach(function (it) {
					var ok = !q || norm(it.dataset.t).indexOf(q) !== -1;
					it.classList.toggle('lp-no', !ok);
					if (ok) { n++; }
				});
				g.classList.toggle('lp-no', !!q && !n);
				if (g.tagName === 'DETAILS') { g.open = q ? n > 0 : g.dataset.abierto === '1'; }
				hay += n;
			});
			nada.hidden = !q || hay > 0;
			nada.textContent = q && !hay ? 'Nada con «' + buscar.value.trim() + '».' : '';
		});
	}

	// Resaltado del contenido recién guardado en la lista.
	var r = document.querySelector('.lp-item-resaltado');
	if (r) { r.scrollIntoView({ block: 'center' }); }
})();
