import { createEventType, listBookings, listEventTypes } from './sdk.gen.js';

const emptyState = document.getElementById('empty-state');
const tableWrap = document.getElementById('table-wrap');
const tableBody = document.getElementById('event-types-body');
const listError = document.getElementById('list-error');
const tabs = document.querySelectorAll('.admin-tab');
const bookingsLoading = document.getElementById('bookings-loading');
const bookingsError = document.getElementById('bookings-error');
const bookingsEmpty = document.getElementById('bookings-empty');
const bookingDays = document.getElementById('booking-days');
const dialog = document.getElementById('create-dialog');
const form = document.getElementById('create-form');
const createError = document.getElementById('create-error');
const openButton = document.getElementById('open-create');
const cancelButton = document.getElementById('cancel-create');
const submitButton = document.getElementById('submit-create');
const idInput = document.getElementById('event-type-id');
const nameInput = document.getElementById('event-type-name');
const durationInput = document.getElementById('event-type-duration');
const descriptionInput = document.getElementById('event-type-description');

const DUPLICATE_MESSAGE = 'Тип события с таким ID уже существует';
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить типы событий';
const CREATE_ERROR_MESSAGE = 'Не удалось создать тип события';
const LOAD_BOOKINGS_ERROR = 'Не удалось загрузить записи';

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

function createErrorMessage(error) {
  if (error && typeof error === 'object' && error.status === 409) {
    return DUPLICATE_MESSAGE;
  }

  return problemDetail(error) || CREATE_ERROR_MESSAGE;
}

function appendCell(row, text) {
  const cell = document.createElement('td');
  cell.textContent = text;
  row.append(cell);
}

function render(eventTypes) {
  tableBody.replaceChildren();

  const hasRows = eventTypes.length > 0;
  emptyState.hidden = hasRows;
  tableWrap.hidden = !hasRows;

  for (const eventType of eventTypes) {
    const row = document.createElement('tr');

    const idCell = document.createElement('td');
    const slug = document.createElement('code');
    slug.textContent = eventType.id;
    idCell.append(slug);

    row.append(idCell);
    appendCell(row, eventType.name);
    appendCell(row, eventType.description ?? '—');
    appendCell(row, `${eventType.durationMinutes} мин`);

    tableBody.append(row);
  }
}

async function load() {
  hideError(listError);

  const { data, error } = await listEventTypes();

  if (error) {
    showError(listError, problemDetail(error) || LOAD_ERROR_MESSAGE);
    return;
  }

  render(Array.isArray(data) ? data : []);
}

/** Форматы дня и времени берут пояс из ответа сервера, а не из настроек браузера. */
function bookingFormatters(timezone) {
  return {
    dayKey: new Intl.DateTimeFormat('en-CA', {
      timeZone: timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }),
    dayTitle: new Intl.DateTimeFormat('ru-RU', {
      timeZone: timezone,
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    }),
    time: new Intl.DateTimeFormat('ru-RU', {
      timeZone: timezone,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }),
  };
}

/** Строка записи: диапазон времени, бейдж типа события, имя и email Гостя, заметка. */
function renderBooking(booking, formatters) {
  const item = document.createElement('li');
  item.className = 'booking-item';

  const start = new Date(booking.start);
  const end = new Date(start.getTime() + booking.durationMinutes * 60000);

  const time = document.createElement('time');
  time.className = 'booking-time';
  time.dateTime = booking.start;
  time.textContent = `${formatters.time.format(start)}–${formatters.time.format(end)}`;

  const badge = document.createElement('span');
  badge.className = 'badge';
  badge.textContent = booking.eventTypeName;

  const guest = document.createElement('div');
  guest.className = 'booking-guest';

  const name = document.createElement('span');
  name.className = 'booking-name';
  name.textContent = booking.name;

  const email = document.createElement('span');
  email.className = 'booking-email';
  email.textContent = booking.email;

  guest.append(name, email);
  item.append(time, badge, guest);

  if (booking.note) {
    const note = document.createElement('p');
    note.className = 'booking-note';
    note.textContent = booking.note;
    item.append(note);
  }

  return item;
}

/** Список уже отсортирован по start — группы дней идут подряд, без повторов. */
function renderBookings(bookings, timezone) {
  bookingDays.replaceChildren();

  const hasRows = bookings.length > 0;
  bookingsEmpty.hidden = hasRows;
  bookingDays.hidden = !hasRows;

  if (!hasRows) {
    return;
  }

  const formatters = bookingFormatters(timezone);
  let currentDay = null;
  let list = null;

  for (const booking of bookings) {
    const start = new Date(booking.start);
    const day = formatters.dayKey.format(start);

    if (day !== currentDay) {
      currentDay = day;

      const heading = document.createElement('h3');
      heading.className = 'booking-day';
      heading.textContent = formatters.dayTitle.format(start);

      list = document.createElement('ul');
      list.className = 'booking-list';

      bookingDays.append(heading, list);
    }

    list.append(renderBooking(booking, formatters));
  }
}

async function loadBookings() {
  hideError(bookingsError);
  bookingsEmpty.hidden = true;
  bookingDays.hidden = true;
  bookingsLoading.hidden = false;

  const { data, error } = await listBookings();

  bookingsLoading.hidden = true;

  if (error) {
    showError(bookingsError, problemDetail(error) || LOAD_BOOKINGS_ERROR);
    return;
  }

  if (typeof data?.timezone !== 'string') {
    showError(bookingsError, LOAD_BOOKINGS_ERROR);
    return;
  }

  renderBookings(Array.isArray(data?.bookings) ? data.bookings : [], data.timezone);
}

function selectTab(tab) {
  for (const other of tabs) {
    const active = other === tab;
    other.classList.toggle('active', active);
    other.setAttribute('aria-selected', String(active));
    document.getElementById(other.getAttribute('aria-controls')).hidden = !active;
  }
}

for (const tab of tabs) {
  tab.addEventListener('click', () => selectTab(tab));
}

openButton.addEventListener('click', () => {
  hideError(createError);
  dialog.showModal();
});

cancelButton.addEventListener('click', () => {
  dialog.close();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  hideError(createError);
  submitButton.disabled = true;

  const description = descriptionInput.value.trim();
  const body = {
    id: idInput.value.trim(),
    name: nameInput.value.trim(),
    durationMinutes: Number(durationInput.value),
    description: description === '' ? null : description,
  };

  try {
    const { error } = await createEventType({ body });

    if (error) {
      showError(createError, createErrorMessage(error));
      return;
    }

    dialog.close();
    form.reset();
    await load();
  } finally {
    submitButton.disabled = false;
  }
});

await Promise.all([load(), loadBookings()]);
