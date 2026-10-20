(function () {
  'use strict';

  var METRIKA_ID = 113557563;

  var METRIKA_GOALS = {
    add_to_cart: 'add_to_cart',
    order_complete: 'order_complete',
    review_submit: 'review_submit'
  };

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call(
      (root || document).querySelectorAll(sel)
    );
  }

  function money(value) {
    try {
      return Math.round(value).toLocaleString('ru-RU') + ' ₽';
    } catch (e) {
      return Math.round(value) + ' ₽';
    }
  }

  function metrikaGoal(name, params) {
    var goal = METRIKA_GOALS[name];

    if (!goal) {
      return;
    }

    if (typeof window.ym !== 'function') {
      return;
    }

    window.ym(
      METRIKA_ID,
      'reachGoal',
      goal,
      params || {}
    );
  }


  function flushServerMetrikaGoals() {
    var goals = window.__METRIKA_SERVER_GOALS__;

    if (!Array.isArray(goals) || !goals.length) {
      return;
    }

    goals.forEach(function (item) {
      if (!item || !item.goal) {
        return;
      }

      metrikaGoal(
        String(item.goal),
        item.params && typeof item.params === 'object' ? item.params : {}
      );
    });

    window.__METRIKA_SERVER_GOALS__ = [];
  }

  function escapeHtml(s) {
    return String(s).replace(
      /[&<>"']/g,
      function (c) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        })[c];
      }
    );
  }

  function starsText(rating) {
    var n = Math.max(
      0,
      Math.min(5, Number(rating) || 0)
    );

    return (
      '★★★★★'.slice(0, n) +
      '☆☆☆☆☆'.slice(n)
    );
  }

  function setReviewStatus(form, message, ok) {
    var status = qs(
      '.review-form-status',
      form
    );

    if (!status) {
      return;
    }

    status.textContent = message || '';

    status.classList.remove(
      'is-error',
      'is-ok'
    );

    if (message) {
      status.classList.add(
        ok ? 'is-ok' : 'is-error'
      );
    }
  }

  function updateStarButtons(form, value) {
    qsa(
      '.star-btn',
      form
    ).forEach(
      function (btn) {
        var n =
          parseInt(
            btn.getAttribute('data-star'),
            10
          ) || 0;

        btn.classList.toggle(
          'on',
          n <= value
        );

        btn.setAttribute(
          'aria-checked',
          n === value ? 'true' : 'false'
        );
      }
    );

    var input = qs(
      '.rating-input',
      form
    );

    if (input) {
      input.value = String(value);
    }
  }

  function buildReviewArticle(review) {
    var article =
      document.createElement('article');

    article.className =
      'review in';

    article.setAttribute(
      'data-review-id',
      review.id || ''
    );

    article.setAttribute(
      'itemscope',
      ''
    );

    article.setAttribute(
      'itemtype',
      'https://schema.org/Review'
    );

    article.innerHTML =
      '<div class="review-head">' +

        '<div>' +

          '<strong itemprop="author">' +
            '<span itemprop="name">' +
              escapeHtml(review.name) +
            '</span>' +
          '</strong>' +

          '<span class="verified-badge">' +
            'Проверенная покупка' +
          '</span>' +

        '</div>' +

        '<span itemprop="reviewRating" ' +
              'itemscope ' +
              'itemtype="https://schema.org/Rating">' +

          '<meta itemprop="ratingValue" content="' +
            Number(review.rating) +
          '">' +

          '<meta itemprop="bestRating" content="5">' +
          '<meta itemprop="worstRating" content="1">' +

          '<span class="stars" aria-label="' +
            Number(review.rating) +
            ' из 5">' +

            starsText(review.rating) +

          '</span>' +

        '</span>' +

      '</div>' +

      '<p class="review-text" itemprop="reviewBody">' +
        escapeHtml(review.text) +
      '</p>' +

      '<time class="muted review-date" itemprop="datePublished">' +
        escapeHtml(review.created_at) +
      '</time>';

    return article;
  }

  function updateRatingSummary(root, data) {
    if (!root || !data) {
      return;
    }

    qsa(
      '[data-rating-summary]',
      root
    ).forEach(
      function (summary) {
        var avg = qs(
          '[data-rating-avg]',
          summary
        );

        var count = qs(
          '[data-rating-count]',
          summary
        );

        if (avg) {
          avg.textContent =
            data.count > 0
              ? Number(data.avg).toFixed(1)
              : '—';
        }

        if (count) {
          count.textContent =
            String(data.count || 0);
        }

        if (data.distribution) {
          qsa(
            '[data-rating-row]',
            summary
          ).forEach(
            function (row) {
              var rating =
                row.getAttribute(
                  'data-rating-row'
                );

              var n =
                Number(
                  data.distribution[rating] || 0
                );

              var pct =
                data.count > 0
                  ? Math.round(
                      n / data.count * 100
                    )
                  : 0;

              var bar = qs(
                '.rating-track i',
                row
              );

              var c = qs(
                '[data-rating-row-count]',
                row
              );

              if (bar) {
                bar.style.width =
                  pct + '%';
              }

              if (c) {
                c.textContent =
                  String(n);
              }
            }
          );
        }
      }
    );

    qsa(
      '.reviews-heading-count',
      root
    ).forEach(
      function (el) {
        el.textContent =
          '(' +
          String(data.count || 0) +
          ')';
      }
    );

    var agg =
      qs(
        '.agg-rating',
        root
      );

    if (
      agg &&
      data.count > 0
    ) {
      var value = qs(
        '[itemprop="ratingValue"]',
        agg
      );

      var countNode = qs(
        '[itemprop="reviewCount"]',
        agg
      );

      var starNode = qs(
        '.stars',
        agg
      );

      if (value) {
        value.textContent =
          Number(data.avg).toFixed(1);
      }

      if (countNode) {
        countNode.textContent =
          String(data.count);
      }

      if (starNode) {
        starNode.textContent =
          starsText(
            Math.round(
              Number(data.avg)
            )
          );
      }
    }
  }

  function insertOrReplaceReview(form, data) {
    var scope =
      form.closest('.product-page') ||
      document;

    var list =
      qs(
        '.reviews-list',
        scope
      );

    if (
      !list ||
      !data.review
    ) {
      return;
    }

    var empty =
      qs(
        '.empty-reviews',
        list
      );

    if (empty) {
      empty.remove();
    }

    var article =
      buildReviewArticle(
        data.review
      );

    var existing =
      data.review.id
        ? qs(
            '[data-review-id="' +
            data.review.id +
            '"]',
            list
          )
        : null;

    if (existing) {
      existing.replaceWith(article);
    } else {
      list.insertBefore(
        article,
        list.firstChild
      );
    }

    updateRatingSummary(
      scope,
      data
    );
  }

  document.addEventListener(
    'click',
    function (e) {
      var navToggle =
        e.target.closest(
          '.nav-toggle'
        );

      if (navToggle) {
        var nav =
          qs('.nav');

        if (nav) {
          var isOpen =
            nav.classList.toggle(
              'open'
            );

          navToggle.setAttribute(
            'aria-expanded',
            isOpen
              ? 'true'
              : 'false'
          );
        }
      }

      var chip =
        e.target.closest(
          '[data-pick]'
        );

      if (chip) {
        var name =
          chip.getAttribute(
            'data-pick'
          );

        qsa(
          '[data-pick="' +
          name +
          '"]',
          chip.parentElement
        ).forEach(
          function (n) {
            n.classList.remove('on');
          }
        );

        chip.classList.add('on');

        var input =
          document.getElementById(
            name
          );

        if (input) {
          input.value =
            chip.getAttribute(
              'data-value'
            );
        }

        var price =
          chip.getAttribute(
            'data-price'
          );

        var live =
          document.getElementById(
            'live-price'
          );

        if (
          price &&
          live
        ) {
          var regular =
            Number(price);

          var promoDiscount =
            Number(
              live.getAttribute(
                'data-promo-discount'
              ) || 0
            );

          var actual =
            Math.max(
              0,
              regular -
              promoDiscount
            );

          var priceSpan =
            qs(
              '[itemprop="price"]',
              live
            ) || live;

          priceSpan.textContent =
            actual.toLocaleString(
              'ru-RU'
            ) + ' ₽';

          if (
            priceSpan.setAttribute
          ) {
            priceSpan.setAttribute(
              'content',
              actual.toFixed(2)
            );
          }

          var regularSpan =
            qs(
              '[data-regular-price-display]',
              live
            );

          if (regularSpan) {
            regularSpan.textContent =
              regular.toLocaleString(
                'ru-RU'
              ) + ' ₽';
          }

          var promoPercentDisplay =
            qs(
              '[data-promo-percent-display]'
            );

          if (
            promoPercentDisplay &&
            regular > 0 &&
            actual < regular
          ) {
            promoPercentDisplay.textContent =
              '−' +
              String(
                Math.round(
                  (
                    1 -
                    actual / regular
                  ) * 100
                )
              ) +
              '%';
          }
        }
      }

      var starBtn =
        e.target.closest(
          '.star-btn'
        );

      if (starBtn) {
        e.preventDefault();

        var form =
          starBtn.closest('form');

        var value =
          parseInt(
            starBtn.getAttribute(
              'data-star'
            ),
            10
          ) || 0;

        if (form) {
          updateStarButtons(
            form,
            value
          );

          setReviewStatus(
            form,
            '',
            false
          );
        }
      }
    }
  );

  document.addEventListener(
    'submit',
    function (e) {
      var form =
        e.target.closest(
          '[data-ajax-review]'
        );

      if (!form) {
        return;
      }

      e.preventDefault();

      var rating =
        parseInt(
          (
            qs(
              '.rating-input',
              form
            ) || {}
          ).value || '0',
          10
        );

      var text =
        (
          qs(
            'textarea[name="text"]',
            form
          ) || {}
        ).value || '';

      text = text.trim();

      if (
        rating < 1 ||
        rating > 5
      ) {
        setReviewStatus(
          form,
          'Выберите оценку от 1 до 5 звёзд.',
          false
        );

        return;
      }

      if (
        text.length < 3
      ) {
        setReviewStatus(
          form,
          'Текст отзыва — минимум 3 символа.',
          false
        );

        return;
      }

      if (
        text.length > 2000
      ) {
        setReviewStatus(
          form,
          'Текст отзыва — максимум 2000 символов.',
          false
        );

        return;
      }

      var submit =
        qs(
          'button[type="submit"]',
          form
        );

      if (submit) {
        submit.disabled = true;
      }

      setReviewStatus(
        form,
        'Сохраняем…',
        true
      );

      var fd =
        new FormData(form);

      var endpoint =
        form.getAttribute(
          'data-api-url'
        ) ||
        form.action;

      fetch(
        endpoint,
        {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',

          headers: {
            'X-Requested-With':
              'XMLHttpRequest'
          }
        }
      )

        .then(
          function (r) {
            return r
              .json()
              .then(
                function (j) {
                  return {
                    ok: r.ok,
                    j: j
                  };
                }
              );
          }
        )

        .then(
          function (res) {
            if (
              !res.j ||
              !res.j.ok
            ) {
              setReviewStatus(
                form,
                (
                  res.j &&
                  res.j.error
                ) ||
                'Не удалось сохранить отзыв.',
                false
              );

              return;
            }

            setReviewStatus(
              form,
              res.j.message ||
              'Сохранено',
              true
            );

            insertOrReplaceReview(
              form,
              res.j
            );

            metrikaGoal(
              'review_submit',
              {
                product_id:
                  String(
                    (
                      qs(
                        'input[name="product_id"]',
                        form
                      ) || {}
                    ).value || ''
                  ),

                rating:
                  rating
              }
            );

            if (submit) {
              submit.textContent =
                'Обновить отзыв';
            }
          }
        )

        .catch(
          function () {
            setReviewStatus(
              form,
              'Не удалось отправить. Проверьте соединение и попробуйте ещё раз.',
              false
            );
          }
        )

        .finally(
          function () {
            if (submit) {
              submit.disabled =
                false;
            }
          }
        );
    }
  );

  qsa(
    '[data-confirm]'
  ).forEach(
    function (el) {
      el.addEventListener(
        'click',
        function (e) {
          if (
            !window.confirm(
              el.getAttribute(
                'data-confirm'
              )
            )
          ) {
            e.preventDefault();
          }
        }
      );
    }
  );

  var file =
    qs(
      'input[name="image_file"]'
    );

  if (file) {
    file.addEventListener(
      'change',
      function () {
        if (
          !file.files ||
          !file.files[0]
        ) {
          return;
        }

        var prev =
          qs('.preview');

        if (!prev) {
          prev =
            document.createElement(
              'img'
            );

          prev.className =
            'preview';

          file.parentNode.insertBefore(
            prev,
            file
          );
        }

        prev.src =
          URL.createObjectURL(
            file.files[0]
          );
      }
    );
  }

  (function revealOnScroll() {
    var nodes =
      qsa(
        '.card, .cat, .tile, .section h2, .section .sub, .gallery, .product h1, .box, .review'
      );

    if (!nodes.length) {
      return;
    }

    if (
      !(
        'IntersectionObserver'
        in window
      )
    ) {
      nodes.forEach(
        function (n) {
          n.classList.add('in');
        }
      );

      return;
    }

    var io =
      new IntersectionObserver(
        function (entries) {
          entries.forEach(
            function (entry) {
              if (
                !entry.isIntersecting
              ) {
                return;
              }

              entry.target.classList.add(
                'in'
              );

              io.unobserve(
                entry.target
              );
            }
          );
        },
        {
          threshold: 0.05,
          rootMargin:
            '0px 0px -20px 0px'
        }
      );

    var groups =
      new Map();

    nodes.forEach(
      function (node) {
        var parent =
          node.closest(
            '.grid, .cats, .hero-split, .section, .product, .reviews'
          ) ||
          document.body;

        if (
          !groups.has(parent)
        ) {
          groups.set(
            parent,
            []
          );
        }

        groups
          .get(parent)
          .push(node);
      }
    );

    groups.forEach(
      function (group) {
        group.forEach(
          function (node, i) {
            node.classList.add(
              'reveal'
            );

            node.style.transitionDelay =
              Math.min(
                i * 0.05,
                0.35
              ) + 's';

            io.observe(node);
          }
        );
      }
    );
  })();

  (function phoneMask() {
    var inputs =
      qsa(
        'input.phone-mask, input[name="phone"][type="tel"]'
      );

    if (!inputs.length) {
      return;
    }

    function format(v) {
      var d =
        v.replace(
          /\D/g,
          ''
        );

      if (
        d.indexOf('8') === 0
      ) {
        d =
          '7' +
          d.slice(1);
      }

      if (
        d.indexOf('7') !== 0
      ) {
        d =
          '7' +
          d;
      }

      d =
        d.slice(
          0,
          11
        );

      var r = '+7';

      if (
        d.length > 1
      ) {
        r +=
          ' (' +
          d.slice(
            1,
            4
          );
      }

      if (
        d.length >= 4
      ) {
        r +=
          ') ' +
          d.slice(
            4,
            7
          );
      }

      if (
        d.length >= 7
      ) {
        r +=
          '-' +
          d.slice(
            7,
            9
          );
      }

      if (
        d.length >= 9
      ) {
        r +=
          '-' +
          d.slice(
            9,
            11
          );
      }

      return r;
    }

    inputs.forEach(
      function (input) {
        input.addEventListener(
          'input',
          function () {
            input.value =
              format(
                input.value
              );
          }
        );

        input.addEventListener(
          'focus',
          function () {
            if (
              !input.value
            ) {
              input.value =
                '+7 (';
            }
          }
        );
      }
    );
  })();

  (function checkoutDelivery() {
    var firstRadio =
      qs(
        'form [name="delivery_method"]'
      );

    var form =
      firstRadio
        ? firstRadio.form
        : null;

    if (!form) {
      return;
    }

    var radios =
      qsa(
        'input[name="delivery_method"]',
        form
      );

    var addressWrap =
      qs(
        '[data-delivery-address]',
        form
      );

    var addressFields =
      qsa(
        '[data-address-field]',
        form
      );

    var total =
      qs(
        '[data-checkout-total]'
      );

    var shippingRow =
      qs(
        '[data-shipping-row]'
      );

    var shippingPrice =
      qs(
        '[data-shipping-price]'
      );

    function refresh() {
      var checked =
        qs(
          'input[name="delivery_method"]:checked',
          form
        );

      var delivery =
        !checked ||
        checked.value ===
          'delivery';

      if (addressWrap) {
        addressWrap.hidden =
          !delivery;
      }

      addressFields.forEach(
        function (field) {
          var required =
            field.name ===
              'city' ||
            field.name ===
              'street' ||
            field.name ===
              'house';

          field.required =
            delivery &&
            required;

          field.disabled =
            !delivery;
        }
      );

      qsa(
        '.delivery-option',
        form
      ).forEach(
        function (option) {
          var input =
            qs(
              'input[type="radio"]',
              option
            );

          option.classList.toggle(
            'is-selected',
            !!input &&
            input.checked
          );
        }
      );

      if (shippingRow) {
        shippingRow.hidden =
          !delivery;
      }

      if (total) {
        var base =
          parseFloat(
            total.getAttribute(
              'data-base-total'
            ) || '0'
          ) || 0;

        var fee =
          parseFloat(
            total.getAttribute(
              'data-delivery-shipping'
            ) || '0'
          ) || 0;

        total.textContent =
          money(
            base +
            (
              delivery
                ? fee
                : 0
            )
          );

        var submit =
          qs(
            'button[type="submit"]',
            form
          );

        if (submit) {
          submit.textContent =
            'Подтвердить заказ на ' +
            money(
              base +
              (
                delivery
                  ? fee
                  : 0
              )
            );
        }

        if (shippingPrice) {
          shippingPrice.textContent =
            money(fee);
        }
      }
    }

    radios.forEach(
      function (radio) {
        radio.addEventListener(
          'change',
          refresh
        );
      }
    );

    refresh();
  })();

  document
    .querySelectorAll(
      '.cart-update-form .qty'
    )
    .forEach(
      function (input) {
        input.addEventListener(
          'change',
          function () {
            var form =
              input.closest(
                '.cart-update-form'
              );

            if (form) {
              form.submit();
            }
          }
        );
      }
    );

  (function promoEditor() {
    var form =
      qs(
        '[data-promo-form]'
      );

    if (!form) {
      return;
    }

    var types =
      Array.prototype.slice.call(
        form.querySelectorAll(
          '[data-promo-type]'
        )
      );

    var start =
      qs(
        '[data-promo-start]',
        form
      );

    var end =
      qs(
        '[data-promo-end]',
        form
      );

    var periodText =
      qs(
        '[data-promo-period-text]',
        form
      );

    var durationText =
      qs(
        '[data-promo-duration-text]',
        form
      );

    var product =
      qs(
        '[data-promo-product]',
        form
      );

    var promoPrice =
      qs(
        '[data-promo-price]',
        form
      );

    var priceHint =
      qs(
        '[data-promo-price-hint]',
        form
      );

    var imageBox =
      qs(
        '[data-promo-image-box]',
        form
      );

    var image =
      qs(
        '[data-promo-image-preview]',
        form
      );

    var imageEmpty =
      qs(
        '[data-promo-image-empty]',
        form
      );

    var imageUrl =
      qs(
        '[data-promo-image-url]',
        form
      );

    var imageFile =
      qs(
        '[data-promo-image-file]',
        form
      );

    function selectedType() {
      for (
        var i = 0;
        i < types.length;
        i++
      ) {
        if (
          types[i].checked
        ) {
          return types[i];
        }
      }

      return (
        types[0] ||
        null
      );
    }

    function pad(value) {
      return String(value).padStart(
        2,
        '0'
      );
    }

    function formatIsoUtc(date) {
      return (
        date.getUTCFullYear() +
        '-' +
        pad(
          date.getUTCMonth() + 1
        ) +
        '-' +
        pad(
          date.getUTCDate()
        )
      );
    }

    function formatRuUtc(date) {
      return (
        pad(
          date.getUTCDate()
        ) +
        '.' +
        pad(
          date.getUTCMonth() + 1
        ) +
        '.' +
        date.getUTCFullYear()
      );
    }

    function durationLabel(days) {
      var mod10 =
        days % 10;

      var mod100 =
        days % 100;

      if (
        mod10 === 1 &&
        mod100 !== 11
      ) {
        return (
          days +
          ' день'
        );
      }

      if (
        mod10 >= 2 &&
        mod10 <= 4 &&
        (
          mod100 < 12 ||
          mod100 > 14
        )
      ) {
        return (
          days +
          ' дня'
        );
      }

      return (
        days +
        ' дней'
      );
    }

    function parseIso(value) {
      var parts =
        String(
          value || ''
        ).split('-');

      if (
        parts.length !== 3
      ) {
        return null;
      }

      return new Date(
        Date.UTC(
          Number(parts[0]),
          Number(parts[1]) - 1,
          Number(parts[2])
        )
      );
    }

    function updatePeriod() {
      if (
        !start ||
        !end
      ) {
        return;
      }

      if (
        start.value
      ) {
        end.min =
          start.value;
      }

      var startDate =
        parseIso(
          start.value
        );

      var endDate =
        parseIso(
          end.value
        );

      if (
        !startDate ||
        !endDate
      ) {
        return;
      }

      var days =
        Math.floor(
          (
            endDate.getTime() -
            startDate.getTime()
          ) /
          86400000
        ) + 1;

      if (periodText) {
        periodText.textContent =
          formatRuUtc(
            startDate
          ) +
          ' — ' +
          formatRuUtc(
            endDate
          );
      }

      if (durationText) {
        durationText.textContent =
          days > 0
            ? durationLabel(days) +
              ' включительно'
            : 'Проверьте даты: окончание раньше начала';
      }
    }

    function applyDefaultPeriod() {
      if (
        !start ||
        !end ||
        !start.value
      ) {
        return;
      }

      var type =
        selectedType();

      if (!type) {
        return;
      }

      var startDate =
        parseIso(
          start.value
        );

      if (!startDate) {
        return;
      }

      var days =
        parseInt(
          type.getAttribute(
            'data-duration'
          ) || '1',
          10
        ) || 1;

      var endDate =
        new Date(
          startDate.getTime() +
          (
            days - 1
          ) *
          86400000
        );

      end.value =
        formatIsoUtc(
          endDate
        );

      updatePeriod();
    }

    function updatePriceHint() {
      if (
        !product ||
        !priceHint
      ) {
        return;
      }

      var option =
        product.options[
          product.selectedIndex
        ];

      if (
        !option ||
        !option.value
      ) {
        priceHint.textContent =
          'Выберите товар, чтобы увидеть обычную цену.';

        return;
      }

      var regular =
        parseFloat(
          option.getAttribute(
            'data-price'
          ) || '0'
        ) || 0;

      var special =
        promoPrice
          ? (
              parseFloat(
                promoPrice.value ||
                '0'
              ) || 0
            )
          : 0;

      if (
        special > 0 &&
        regular > 0 &&
        special < regular
      ) {
        priceHint.textContent =
          'Обычная цена: ' +
          money(regular) +
          ' · экономия ' +
          money(
            regular -
            special
          );
      } else if (
        regular > 0
      ) {
        priceHint.textContent =
          'Обычная цена товара: ' +
          money(regular);
      }
    }

    function setPreview(src) {
      if (
        !image ||
        !imageBox ||
        !imageEmpty
      ) {
        return;
      }

      if (src) {
        image.src =
          src;

        image.hidden =
          false;

        imageEmpty.hidden =
          true;

        imageBox.classList.remove(
          'is-empty'
        );
      } else {
        image.removeAttribute(
          'src'
        );

        image.hidden =
          true;

        imageEmpty.hidden =
          false;

        imageBox.classList.add(
          'is-empty'
        );
      }
    }

    function productImage() {
      if (!product) {
        return '';
      }

      var option =
        product.options[
          product.selectedIndex
        ];

      return (
        option &&
        option.value
          ? (
              option.getAttribute(
                'data-image'
              ) || ''
            )
          : ''
      );
    }

    function refreshFallbackImage() {
      if (
        imageUrl &&
        imageUrl.value.trim()
      ) {
        setPreview(
          imageUrl.value.trim()
        );

        return;
      }

      setPreview(
        productImage()
      );
    }

    types.forEach(
      function (input) {
        input.addEventListener(
          'change',
          applyDefaultPeriod
        );
      }
    );

    if (start) {
      start.addEventListener(
        'change',
        applyDefaultPeriod
      );
    }

    if (end) {
      end.addEventListener(
        'change',
        updatePeriod
      );
    }

    if (product) {
      product.addEventListener(
        'change',
        function () {
          updatePriceHint();
          refreshFallbackImage();
        }
      );
    }

    if (promoPrice) {
      promoPrice.addEventListener(
        'input',
        updatePriceHint
      );
    }

    if (imageUrl) {
      imageUrl.addEventListener(
        'input',
        refreshFallbackImage
      );
    }

    if (imageFile) {
      imageFile.addEventListener(
        'change',
        function () {
          var file =
            imageFile.files &&
            imageFile.files[0];

          if (!file) {
            refreshFallbackImage();
            return;
          }

          var reader =
            new FileReader();

          reader.onload =
            function () {
              setPreview(
                String(
                  reader.result ||
                  ''
                )
              );
            };

          reader.readAsDataURL(
            file
          );
        }
      );
    }

    updatePeriod();
    updatePriceHint();

    if (
      !image ||
      image.hidden
    ) {
      refreshFallbackImage();
    }
  })();


  flushServerMetrikaGoals();

})();