
CREATE TABLE IF NOT EXISTS model_2 (
  id serial NOT NULL,
  int1 int DEFAULT NULL,
  str1 varchar(128),
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS model_1 (
  id serial NOT NULL,
  int1 int,
  str1 varchar(64),
  model2_id int DEFAULT NULL,
  bool1 boolean NOT NULL default True,
  array1 int[] DEFAULT NULL,
  timestamp1 timestamp DEFAULT NULL,
  json1 jsonb default null,
  PRIMARY KEY (id),

  FOREIGN KEY (model2_id) REFERENCES model_2 (id) ON DELETE CASCADE ON UPDATE CASCADE
);
