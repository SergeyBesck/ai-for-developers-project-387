import "@typespec/http";
import "@typespec/openapi";
import "@typespec/openapi3";

using Http;
using OpenAPI;

@service(#{ title: "Calendar" })
@server("/api", "Calendar API")
@doc("Запись на звонок: типы событий, слоты, записи.")
@info(#{ version: "1.0.0" })
namespace Calendar;

// ===== Схемы =====

@doc("Тип события: вид встречи, на который идёт запись.")
model EventType {
  @doc("Slug-идентификатор, уникален.")
  @pattern("^[a-z0-9]+(-[a-z0-9]+)*$")
  id: string;

  name: string;

  @doc("Описание; null, если не задано.")
  description: string | null;

  @doc("Длительность в минутах, 1..540 (длина рабочего дня).")
  @minValue(1)
  @maxValue(540)
  durationMinutes: int32;
}

model EventTypeCreate {
  @pattern("^[a-z0-9]+(-[a-z0-9]+)*$")
  id: string;

  @minLength(1)
  name: string;

  description?: string | null;

  @minValue(1)
  @maxValue(540)
  durationMinutes: int32;
}

@doc("Слот: начало возможной записи в сетке календаря.")
model Slot {
  @doc("Начало слота: ISO 8601 с обязательным смещением локального пояса приложения.")
  start: offsetDateTime;

  available: boolean;
}

model SlotsResponse {
  @doc("IANA-идентификатор часового пояса приложения, например Europe/Moscow.")
  timezone: string;

  slots: Slot[];
}

model BookingCreate {
  @doc("Slug выбранного типа события.")
  @pattern("^[a-z0-9]+(-[a-z0-9]+)*$")
  eventTypeId: string;

  @doc("Начало слота: ISO 8601 с обязательным смещением; без смещения — 400.")
  start: offsetDateTime;

  @minLength(1)
  @maxLength(100)
  name: string;

  @format("email")
  email: string;

  @maxLength(500)
  note?: string | null;
}

model Booking {
  @format("uuid")
  id: string;

  eventTypeId: string;

  eventTypeName: string;

  start: offsetDateTime;

  durationMinutes: int32;

  @minLength(1)
  @maxLength(100)
  name: string;

  @format("email")
  email: string;

  note: string | null;
}

model BookingsResponse {
  @doc("IANA-идентификатор часового пояса приложения.")
  timezone: string;

  bookings: Booking[];
}

@doc("Ошибка в формате RFC 9457 (application/problem+json).")
model Problem {
  @doc("URI-идентификатор типа проблемы.")
  type: string;

  title: string;

  status: int32;

  detail?: string;
}

// ===== Ответы =====

model CreatedEventType {
  @statusCode code: 201;
  @body eventType: EventType;
}

model CreatedBooking {
  @statusCode code: 201;
  @body booking: Booking;
}

model Problem400 {
  @statusCode code: 400;
  @header contentType: "application/problem+json";
  @body problem: Problem;
}

model Problem404 {
  @statusCode code: 404;
  @header contentType: "application/problem+json";
  @body problem: Problem;
}

model Problem409 {
  @statusCode code: 409;
  @header contentType: "application/problem+json";
  @body problem: Problem;
}

model Problem422 {
  @statusCode code: 422;
  @header contentType: "application/problem+json";
  @body problem: Problem;
}

// ===== Эндпоинты =====

@tag("EventTypes")
@get
@route("/event-types")
@doc("Список типов событий: видит Гость и Хост.")
op listEventTypes(): {
  @body eventTypes: EventType[];
};

@tag("EventTypes")
@post
@route("/event-types")
@doc("Создание типа события Хостом.")
op createEventType(@body body: EventTypeCreate): CreatedEventType | Problem400 | Problem409;

@tag("EventTypes")
@get
@route("/event-types/{id}")
@doc("Детали типа события.")
op getEventType(@path id: string): EventType | Problem404;

@tag("Slots")
@get
@route("/event-types/{id}/slots")
@doc("Слоты на окно записи (от текущего момента, округлённого вверх до 15 минут, до конца 14-го календарного дня в поясе приложения).")
op listSlots(@path id: string): SlotsResponse | Problem404;

@tag("Bookings")
@post
@route("/bookings")
@doc("Создание записи Гостем на свободный слот. 404 — тип события не найден.")
op createBooking(@body body: BookingCreate): CreatedBooking | Problem400 | Problem404 | Problem409 | Problem422;

@tag("Bookings")
@get
@route("/bookings")
@doc("Предстоящие записи всех типов событий, отсортированы по start.")
op listBookings(): BookingsResponse;
