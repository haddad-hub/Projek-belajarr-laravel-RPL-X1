#include <algorithm>
#include <chrono>
#include <cctype>
#include <ctime>
#include <iomanip>
#include <iostream>
#include <random>
#include <sstream>
#include <string>
#include <vector>

using namespace std;

const string STORAGE_KEY = "bonjek_ops_data_v1";

struct Courier {
  string id;
  string username;
  string name;
  string phone;
  string vehicle;
  string photo;
};

struct Finance {
  string id;
  string orderId;
  string date;
  string category;
  string type;
  double amount = 0;
  string note;
  string courierId;
  string courierName;
};

struct Order {
  string id;
  string date;
  string createdAt;
  string customerId;
  string customerUsername;
  string customerName;
  string customerPhone;
  string item;
  string category;
  int qty = 1;
  string pickup;
  string dropoff;
  string note;
  string status;
  string courierId;
  string courierName;
  double revenue = 0;
  string completedAt;
  string acceptedAt;
  string arrivedAt;
};

struct BonjekData {
  vector<Order> orders;
  vector<Finance> finance;
  vector<Courier> couriers;
};

class BonjekStore {
 public:
  BonjekData read() const { return data; }

  void write(const BonjekData& newData) { data = newData; }

  Courier upsertCourier(Courier profile) {
    BonjekData current = read();
    Courier courier;

    courier.id = profile.id.empty() ? "KURIR-UTAMA" : profile.id;
    courier.username = trim(profile.username);
    courier.name = trim(profile.name.empty() ? "Kurir Bonjek" : profile.name);
    courier.phone = trim(profile.phone);
    courier.vehicle = trim(profile.vehicle.empty() ? "Motor" : profile.vehicle);
    courier.photo = profile.photo;

    auto item = find_if(current.couriers.begin(), current.couriers.end(),
                        [&](const Courier& value) {
                          return value.id == courier.id;
                        });

    if (item != current.couriers.end()) {
      *item = courier;
    } else {
      current.couriers.push_back(courier);
    }

    write(current);
    return courier;
  }

  Order createOrder(Order input) {
    BonjekData current = read();
    string orderDate = input.date.empty() ? today() : input.date;

    Order order;
    order.id = makeId("ORD");
    order.date = orderDate;
    order.createdAt = orderDate + "T" + currentTime();
    order.customerId = trim(input.customerId);
    order.customerUsername = trim(input.customerUsername);
    order.customerName =
        trim(input.customerName.empty() ? "Pelanggan" : input.customerName);
    order.customerPhone = trim(input.customerPhone);
    order.item = trim(input.item.empty() ? "Pesanan" : input.item);
    order.category = trim(input.category.empty() ? "Makanan" : input.category);
    order.qty = max(1, input.qty);
    order.pickup = trim(input.pickup);
    order.dropoff = trim(input.dropoff);
    order.note = trim(input.note);
    order.status = "new";
    order.courierId = "";
    order.courierName = "";
    order.revenue = 0;
    order.completedAt = "";

    current.orders.insert(current.orders.begin(), order);
    write(current);
    return order;
  }

  bool acceptOrder(const string& orderId, Courier courier, Order& result) {
    BonjekData current = read();
    auto order = findOrder(current, orderId);

    if (order == current.orders.end() || order->status != "new") {
      return false;
    }

    Courier savedCourier = upsertCourier(courier);
    BonjekData fresh = read();
    auto target = findOrder(fresh, orderId);

    if (target == fresh.orders.end() || target->status != "new") {
      return false;
    }

    target->status = "accepted";
    target->courierId = savedCourier.id;
    target->courierName = savedCourier.name;
    target->acceptedAt = isoNow();

    result = *target;
    write(fresh);
    return true;
  }

  bool completeOrder(const string& orderId, double amount, Order& result) {
    BonjekData current = read();
    auto order = findOrder(current, orderId);

    if (order == current.orders.end() || order->status != "accepted") {
      return false;
    }

    double nominal = money(amount);
    order->status = "awaiting_confirmation";
    order->revenue = nominal;
    order->arrivedAt = isoNow();
    order->date = order->date.empty() ? today() : order->date;

    result = *order;
    write(current);
    return true;
  }

  bool confirmOrder(const string& orderId, Order& result) {
    BonjekData current = read();
    auto order = findOrder(current, orderId);

    if (order == current.orders.end() ||
        order->status != "awaiting_confirmation") {
      return false;
    }

    double nominal = money(order->revenue);
    order->status = "completed";
    order->completedAt = isoNow();
    order->date = order->date.empty() ? today() : order->date;

    bool exists = any_of(current.finance.begin(), current.finance.end(),
                         [&](const Finance& item) {
                           return item.orderId == order->id &&
                                  item.type == "income";
                         });

    if (!exists) {
      Finance finance;
      finance.id = makeId("FIN");
      finance.orderId = order->id;
      finance.date = order->date;
      finance.category = "Pembayaran Order";
      finance.type = "income";
      finance.amount = nominal;
      finance.note = "Pembayaran " + order->item + " oleh " + order->customerName;
      finance.courierId = order->courierId;
      finance.courierName = order->courierName;

      current.finance.insert(current.finance.begin(), finance);
    }

    result = *order;
    write(current);
    return true;
  }

  BonjekData toAnalyticsPayload() const { return read(); }

 private:
  BonjekData data;

  static string trim(const string& value) {
    size_t start = 0;
    size_t end = value.size();

    while (start < end && isspace(static_cast<unsigned char>(value[start]))) {
      start++;
    }

    while (end > start && isspace(static_cast<unsigned char>(value[end - 1]))) {
      end--;
    }

    return value.substr(start, end - start);
  }

  static string today() {
    time_t rawTime = time(nullptr);
    tm localTime = toLocalTime(rawTime);

    stringstream output;
    output << put_time(&localTime, "%Y-%m-%d");
    return output.str();
  }

  static string currentTime() {
    time_t rawTime = time(nullptr);
    tm localTime = toLocalTime(rawTime);

    stringstream output;
    output << put_time(&localTime, "%H:%M:%S");
    return output.str();
  }

  static string isoNow() {
    time_t rawTime = time(nullptr);
    tm utcTime = toUtcTime(rawTime);

    stringstream output;
    output << put_time(&utcTime, "%Y-%m-%dT%H:%M:%SZ");
    return output.str();
  }

  static tm toLocalTime(time_t rawTime) {
    tm output{};
#ifdef _WIN32
    localtime_s(&output, &rawTime);
#else
    localtime_r(&rawTime, &output);
#endif
    return output;
  }

  static tm toUtcTime(time_t rawTime) {
    tm output{};
#ifdef _WIN32
    gmtime_s(&output, &rawTime);
#else
    gmtime_r(&rawTime, &output);
#endif
    return output;
  }

  static string base36(long long value) {
    const string chars = "0123456789abcdefghijklmnopqrstuvwxyz";

    if (value == 0) {
      return "0";
    }

    string output;
    while (value > 0) {
      output.push_back(chars[value % 36]);
      value /= 36;
    }

    reverse(output.begin(), output.end());
    return output;
  }

  static string randomCode() {
    const string chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    random_device device;
    mt19937 generator(device());
    uniform_int_distribution<int> distribution(0, chars.size() - 1);

    string output;
    for (int index = 0; index < 5; index++) {
      output.push_back(chars[distribution(generator)]);
    }

    return output;
  }

  static string makeId(const string& prefix) {
    auto now = chrono::system_clock::now().time_since_epoch();
    auto millis = chrono::duration_cast<chrono::milliseconds>(now).count();
    return prefix + "-" + base36(millis) + "-" + randomCode();
  }

  static double money(double value) {
    if (value < 0) {
      return 0;
    }

    return value;
  }

  static vector<Order>::iterator findOrder(BonjekData& current,
                                           const string& orderId) {
    return find_if(current.orders.begin(), current.orders.end(),
                   [&](const Order& item) { return item.id == orderId; });
  }
};
