
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
  PRIMARY KEY (id),

  FOREIGN KEY (model2_id) REFERENCES model_2 (id) ON DELETE CASCADE ON UPDATE CASCADE
);
