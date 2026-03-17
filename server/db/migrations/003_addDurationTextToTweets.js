import { columnExists } from "./helpers.js";

export const migration003AddDurationTextToTweets = {
  id: "003_add_duration_text_to_tweets",
  run(db) {
    if (columnExists(db, "tweets", "duration_text")) {
      return;
    }

    db.exec("ALTER TABLE tweets ADD COLUMN duration_text TEXT");
  }
};
