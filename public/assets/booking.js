import { createBooking, listEventTypes, listSlots } from './sdk.gen.js';

const typeStep = document.getElementById('type-step');
const timeStep = document.getElementById('time-step');
const dataStep = document.getElementById('data-step');
const confirmStep = document.getElementById('confirm-step');
const stepTypeMarker = document.getElementById('step-type');
const stepTimeMarker = document.getElementById('step-time');
const stepDataMarker = document.getElementById('step-data');
const stepConfirmMarker = document.getElementById('step-confirm');
const typeGrid = document.getElementById('type-grid');
const typesEmpty = document.getElementById('types-empty');
const typeError = document.getElementById('type-error');
const selectedType = document.getElementById('selected-type');
const backToTypes = document.getElementById('back-to-types');
const calendar = document.getElementById('calendar');
const windowRange = document.getElementById('window-range');
const prevDays = document.getElementById('prev-days');
const nextDays = document.getElementById('next-days');
const daysStrip = document.getElementById('days');
const daySlotsTitle = document.getElementById('day-slots-title');
const daySlots = document.getElementById('day-slots');
const slotsEmpty = document.getElementById('slots-empty');
const slotsError = document.getElementById('slots-error');
const timezoneNote = document.getElementById('timezone-note');
const continueToData = document.getElementById('continue-to-data');
const backToTime = document.getElementById('back-to-time');
const dataSummary = document.getElementById('data-summary');
const bookingForm = document.getElementById('booking-form');
const bookingError = document.getElementById('booking-error');
const nameInput = document.getElementById('guest-name');
const emailInput = document.getElementById('guest-email');
const noteInput = document.getElementById('guest-note');
const nameError = document.getElementById('guest-name-error');
const emailError = document.getElementById('guest-email-error');
const noteError = document.getElementById('guest-note-error');
const submitBooking = document.getElementById('submit-booking');
const confirmType = document.getElementById('confirm-type');
const confirmWhen = document.getElementById('confirm-when');
const confirmName = document.getElementById('confirm-name');
const confirmEmail = document.getElementById('confirm-email');
const confirmNote = document.getElementById('confirm-note');

const DAYS_PER_PAGE = 7;
const LOAD_TYPES_ERROR = 'Не удалось загрузить типы событий';
const LOAD_SLOTS_ERROR = 'Не удалось загрузить слоты';
const BOOKING_ERROR_MESSAGE = 'Не удалось создать запись';
const SLOT_TAKEN_MESSAGE = 'Время уже заняли, выберите другое.';
const FIELD_REQUIRED_MESSAGE = 'Поле обязательно к заполнению';

const STEP_ORDER = ['type', 'time', 'data', 'confirm'];
const stepSections = { type: typeStep, time: timeStep, data: dataStep, confirm: confirmStep };
const stepMarkers = {
  type: stepTypeMarker,
  time: stepTimeMarker,
  data: stepDataMarker,
  confirm: stepConfirmMarker,
};

// Поля формы «Данные»: элемент ошибки рядом с полем и поле ввода.
const formFields = {
  name: { input: nameInput, error: nameError },
  email: { input: emailInput, error: emailError },
  note: { input: noteInput, error: noteError },
};

// Часовой пояс и границы окна приходят только из ответа сервера.
let timezone = null;
let days = [];
let page = 0;
let selectedDayKey = null;
let selectedEventType = null;
let selectedStart = null;

function showError(element, message) {
  element.textContent = message;
  element.hidden = false;
}

function hideError(element) {
  element.textContent = '';
  element.hidden = true;
}

function problemDetail(error) {
  if (error && typeof error === 'object' && typeof error.detail === 'string') {
    return error.detail;
  }

  return '';
}

/** Статус problem+json из ответа SDK; без него — null. */
function problemStatus(error) {
  if (error && typeof error === 'object' && typeof error.status === 'number') {
    return error.status;
  }

  return null;
}

function showFieldError(field, message) {
  showError(formFields[field].error, message);
  formFields[field].input.setAttribute('aria-invalid', 'true');
}

function clearFieldErrors() {
  for (const field of Object.values(formFields)) {
    hideError(field.error);
    field.input.removeAttribute('aria-invalid');
  }
}

/** Поле формы, значение которого совпало с нарушителем из текста ошибки валидатора. */
function fieldByValue(value) {
  if (value === '') {
    return 'name';
  }

  for (const [name, field] of Object.entries(formFields)) {
    if (field.input.value.trim() === value) {
      return name;
    }
  }

  return null;
}

/**
 * Ошибки 400 контрактного валидатора: текст называет свойство или нарушившее значение,
 * по нему ошибка встаёт у соответствующего поля формы.
 */
function applyFieldErrors(error) {
  const detail = problemDetail(error);

  if (!detail) {
    return false;
  }

  const required = detail.match(/Required property '([a-zA-Z]+)'/);

  if (required && formFields[required[1]]) {
    showFieldError(required[1], FIELD_REQUIRED_MESSAGE);
    return true;
  }

  const format = detail.match(/Value '([^']*)' does not match format (\w+)/);

  if (format) {
    const field = fieldByValue(format[1]);

    if (field === 'email') {
      showFieldError('email', 'Проверьте формат email');
      return true;
    }
  }

  const length = detail.match(/Length of '([^']*)' must be shorter or equal to (\d+)/);

  if (length) {
    const field = fieldByValue(length[1]);

    if (field) {
      showFieldError(field, `Не больше ${length[2]} символов`);
      return true;
    }
  }

  if (detail.includes("Length of '' must be longer or equal to 1")) {
    showFieldError('name', 'Укажите имя');
    return true;
  }

  return false;
}

/** 409 или 422: возврат на шаг «Время» с обновлёнными слотами и сообщением. */
async function returnToTimeStep(message) {
  showStep('time');

  if (selectedEventType) {
    await loadSlots(selectedEventType.id);
  }

  showError(slotsError, message);
}

function dayKey(iso) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(iso));
}

function dayLabel(iso) {
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: timezone,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  }).format(new Date(iso));
}

function dayTitle(iso) {
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: timezone,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(new Date(iso));
}

function dayLong(iso) {
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: timezone,
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(iso));
}

function timeLabel(iso) {
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date(iso));
}

/** Дата, диапазон времени и часовой пояс — так запись выглядит на подтверждении. */
function formatRange(iso, durationMinutes) {
  const end = new Date(new Date(iso).getTime() + durationMinutes * 60000).toISOString();

  return `${dayLong(iso)}, ${timeLabel(iso)}–${timeLabel(end)} (${timezone})`;
}

function slotPlural(count) {
  const mod10 = count % 10;
  const mod100 = count % 100;

  if (mod10 === 1 && mod100 !== 11) {
    return `${count} слот`;
  }

  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return `${count} слота`;
  }

  return `${count} слотов`;
}

function showStep(name) {
  const activeIndex = STEP_ORDER.indexOf(name);

  for (const step of STEP_ORDER) {
    const position = STEP_ORDER.indexOf(step);
    stepSections[step].hidden = step !== name;

    const marker = stepMarkers[step];
    marker.classList.toggle('is-active', step === name);
    marker.classList.toggle('is-done', position < activeIndex);
    marker.classList.toggle('is-future', position > activeIndex);

    if (step === name) {
      marker.setAttribute('aria-current', 'step');
    } else {
      marker.removeAttribute('aria-current');
    }
  }
}

function groupByDay(slots) {
  const byKey = new Map();

  for (const slot of slots) {
    const key = dayKey(slot.start);
    let day = byKey.get(key);

    if (!day) {
      day = { key, iso: slot.start, slots: [], free: 0 };
      byKey.set(key, day);
    }

    day.slots.push(slot);

    if (slot.available) {
      day.free += 1;
    }
  }

  return [...byKey.values()];
}

function renderTypes(eventTypes) {
  typeGrid.replaceChildren();
  typesEmpty.hidden = eventTypes.length > 0;

  for (const eventType of eventTypes) {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'type-card-button';

    const head = document.createElement('div');
    head.className = 'type-head';

    const name = document.createElement('h2');
    name.textContent = eventType.name;

    const badge = document.createElement('span');
    badge.className = 'badge';
    badge.textContent = `${eventType.durationMinutes} мин`;

    head.append(name, badge);
    card.append(head);

    if (eventType.description) {
      const description = document.createElement('p');
      description.textContent = eventType.description;
      card.append(description);
    }

    card.addEventListener('click', () => openTimeStep(eventType));
    typeGrid.append(card);
  }
}

function renderDays() {
  daysStrip.replaceChildren();

  const start = page * DAYS_PER_PAGE;

  for (const day of days.slice(start, start + DAYS_PER_PAGE)) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'day-button';
    button.append(document.createTextNode(dayLabel(day.iso)));

    const note = document.createElement('span');
    note.className = 'day-note';
    note.textContent = day.free > 0 ? slotPlural(day.free) : 'нет мест';
    button.append(note);

    // День без свободных слотов неактивен.
    button.disabled = day.free === 0;

    if (day.key === selectedDayKey) {
      button.classList.add('is-selected');
      button.setAttribute('aria-pressed', 'true');
    } else {
      button.setAttribute('aria-pressed', 'false');
    }

    if (day.free > 0) {
      button.addEventListener('click', () => selectDay(day.key));
    }

    daysStrip.append(button);
  }

  prevDays.disabled = page === 0;
  nextDays.disabled = start + DAYS_PER_PAGE >= days.length;
}

function renderDaySlots() {
  daySlots.replaceChildren();

  const day = days.find((item) => item.key === selectedDayKey);

  if (!day) {
    return;
  }

  daySlotsTitle.textContent = dayTitle(day.iso);

  for (const slot of day.slots) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'slot-button';
    button.textContent = timeLabel(slot.start);
    button.disabled = !slot.available;

    if (slot.available) {
      button.addEventListener('click', () => selectSlot(button, slot.start));
    } else {
      button.classList.add('is-busy');
      button.setAttribute('aria-label', `${timeLabel(slot.start)} — занято`);
    }

    daySlots.append(button);
  }
}

function selectSlot(button, start) {
  for (const other of daySlots.querySelectorAll('.slot-button')) {
    other.classList.remove('is-selected');
    other.setAttribute('aria-pressed', 'false');
  }

  button.classList.add('is-selected');
  button.setAttribute('aria-pressed', 'true');

  selectedStart = start;
  continueToData.disabled = false;
}

function selectDay(key) {
  selectedDayKey = key;
  selectedStart = null;
  continueToData.disabled = true;
  renderDays();
  renderDaySlots();
}

function renderConfirmation(booking) {
  confirmType.textContent = `${booking.eventTypeName} · ${booking.durationMinutes} мин`;
  confirmWhen.textContent = formatRange(booking.start, booking.durationMinutes);
  confirmName.textContent = booking.name;
  confirmEmail.textContent = booking.email;
  confirmNote.textContent = booking.note ?? '—';
}

async function openTimeStep(eventType) {
  selectedEventType = eventType;
  selectedStart = null;
  continueToData.disabled = true;
  selectedType.textContent = `${eventType.name} · ${eventType.durationMinutes} мин`;
  showStep('time');
  await loadSlots(eventType.id);
}

async function loadSlots(id) {
  hideError(slotsError);
  slotsEmpty.hidden = true;
  calendar.hidden = true;
  daySlots.replaceChildren();
  daySlotsTitle.textContent = '';
  // Список изменился — прежний выбор слота больше не действителен.
  selectedStart = null;
  continueToData.disabled = true;

  const { data, error } = await listSlots({ path: { id } });

  if (error) {
    showError(slotsError, problemDetail(error) || LOAD_SLOTS_ERROR);
    return;
  }

  const slots = Array.isArray(data?.slots) ? data.slots : [];
  timezone = typeof data?.timezone === 'string' ? data.timezone : timezone;

  if (slots.length === 0) {
    slotsEmpty.hidden = false;
    return;
  }

  days = groupByDay(slots);
  const first = days[0];
  const last = days[days.length - 1];
  windowRange.textContent = `Окно записи: ${dayLabel(first.iso)} — ${dayLabel(last.iso)}`;
  timezoneNote.textContent = `Часовой пояс: ${timezone}. Прошедшее время сегодня скрыто.`;
  calendar.hidden = false;

  const firstFree = days.find((day) => day.free > 0);
  page = firstFree ? Math.floor(days.indexOf(firstFree) / DAYS_PER_PAGE) : 0;
  selectedDayKey = firstFree ? firstFree.key : first.key;

  renderDays();
  renderDaySlots();
}

async function loadTypes() {
  hideError(typeError);

  const { data, error } = await listEventTypes();

  if (error) {
    showError(typeError, problemDetail(error) || LOAD_TYPES_ERROR);
    return;
  }

  renderTypes(Array.isArray(data) ? data : []);
}

backToTypes.addEventListener('click', () => {
  showStep('type');
});

backToTime.addEventListener('click', () => {
  showStep('time');
});

continueToData.addEventListener('click', () => {
  if (!selectedEventType || !selectedStart) {
    return;
  }

  dataSummary.textContent = `${selectedEventType.name} · ${selectedEventType.durationMinutes} мин · ${formatRange(selectedStart, selectedEventType.durationMinutes)}`;
  showStep('data');
});

bookingForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  hideError(bookingError);
  clearFieldErrors();
  submitBooking.disabled = true;

  const note = noteInput.value.trim();
  const { data, error } = await createBooking({
    body: {
      eventTypeId: selectedEventType.id,
      start: selectedStart,
      name: nameInput.value.trim(),
      email: emailInput.value.trim(),
      note: note === '' ? null : note,
    },
  });

  submitBooking.disabled = false;

  if (!error) {
    renderConfirmation(data);
    showStep('confirm');
    return;
  }

  const status = problemStatus(error);

  if (status === 409 || status === 422) {
    await returnToTimeStep(status === 409 ? SLOT_TAKEN_MESSAGE : problemDetail(error) || SLOT_TAKEN_MESSAGE);
    return;
  }

  if (status === 400) {
    if (!applyFieldErrors(error)) {
      showError(bookingError, problemDetail(error) || 'Проверьте форму: данные не прошли проверку');
    }

    return;
  }

  showError(bookingError, problemDetail(error) || BOOKING_ERROR_MESSAGE);
});

prevDays.addEventListener('click', () => {
  if (page > 0) {
    page -= 1;
    renderDays();
  }
});

nextDays.addEventListener('click', () => {
  if ((page + 1) * DAYS_PER_PAGE < days.length) {
    page += 1;
    renderDays();
  }
});

showStep('type');
await loadTypes();
