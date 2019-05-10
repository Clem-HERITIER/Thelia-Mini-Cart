import { createStore, applyMiddleware, combineReducers } from "redux";
import { createLogger } from "redux-logger";
import ReduxThunk from "redux-thunk";
import cart from "./modules/cart";

const loggerMiddleware = createLogger(); // initialize logger

const createStoreWithMiddleware = applyMiddleware(
  ...[ReduxThunk, loggerMiddleware]
)(createStore);

const reducer = combineReducers({
  cart
});

const configureStore = initialState =>
  createStoreWithMiddleware(reducer, initialState);
export default configureStore;
