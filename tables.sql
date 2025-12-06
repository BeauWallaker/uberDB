CREATE TABLE Users (
    user_id INT PRIMARY KEY,
    fname VARCHAR(100),
    lname VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address VARCHAR(100),
    longitude DECIMAL(10,6),
    latitude DECIMAL(10,6)
);


CREATE TABLE Driver (
    driver_id INT PRIMARY KEY,
    fname VARCHAR(100),
    lname VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address VARCHAR(100),
    longitude DECIMAL(10,6),
    latitude DECIMAL(10,6),
    country varchar(50),
    radius DECIMAL(10,6)
);



CREATE TABLE Restaurants (
    restaurant_id INT PRIMARY KEY,
    restaurant_name VARCHAR(100),
    address VARCHAR(200)
);



CREATE TABLE Restaurant_Order (
    rest_order_id INT PRIMARY KEY,
    user_id INT,
    restaurant_id INT,
    date_of_delivery varchar(15),
    order_time varchar(15),
    delivery_time varchar(15),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (restaurant_id) REFERENCES Restaurants(restaurant_id)
);



CREATE TABLE Restaurant_Delivery (
    rest_order_id INT,
    user_id INT,
    driver_id INT,
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (driver_id) REFERENCES Driver(driver_id),
    FOREIGN KEY (rest_order_id) REFERENCES Restaurant_Order(rest_order_ID)
);


CREATE TABLE Grocery_Store (
    store_id INT PRIMARY KEY,
    name VARCHAR(100),
    address VARCHAR(200)
);



CREATE TABLE Grocery_Order (
    groc_order_id INT PRIMARY KEY,
    user_id INT,
    store_id INT,
    date_of_delivery varchar(15),
    order_time varchar(15),
    delivery_time varchar(15),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (store_id) REFERENCES Grocery_Store(store_id)
);



CREATE TABLE Grocery_Delivery (
    groc_order_id INT,
    user_id INT,
    driver_id INT,
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (driver_id) REFERENCES Driver(driver_id),
    FOREIGN KEY (groc_order_id) REFERENCES Grocery_Order(groc_order_id)
);



CREATE TABLE Ride (
    ride_id INT PRIMARY KEY,
    user_id INT,
    driver_id INT,
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (driver_id) REFERENCES Driver(driver_id)
);



CREATE TABLE Ride_details (
    ride_id INT,
    distance INT,
    duration_minutes INT,
    zip varchar(15),
    date_of_ride varchar(15),
    FOREIGN KEY (ride_id) REFERENCES Ride(ride_id)
);



CREATE TABLE Car_details (
    car_id INT PRIMARY KEY,
    make VARCHAR(50),
    model VARCHAR(50),
    car_year INT,
    license_plate VARCHAR(20),
    price_per_hour INT
);



CREATE TABLE Rental (
    rental_id INT PRIMARY KEY,
    user_id INT,
    car_id INT,
    hours_rented INT,
    date_of_rental varchar(15),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (car_id) REFERENCES Car_details(car_id)
);

CREATE TABLE DriverReview (
    ride_id INT,
    user_id INT,
    driver_id INT,
    rating INT,
    comments VARCHAR(500),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (driver_id) REFERENCES Driver(driver_id),
    FOREIGN KEY (ride_id) REFERENCES Ride(ride_id)
);


CREATE TABLE UserReview (
    ride_id INT,
    driver_id INT,
    user_id INT,
    rating INT,
    comments varchar(500),
    FOREIGN KEY (driver_id) REFERENCES Driver(driver_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (ride_id) REFERENCES Ride(ride_id)
);
