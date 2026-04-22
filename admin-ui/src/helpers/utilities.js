import React from "react";
import {CommonContext} from "../Context";

export const alertifyToast = (message, isSuccess = true) => {
    alertify.set('notifier', 'position', 'top-right');
    alertify[isSuccess ? "success" : "error"](message);
}

export const errorDisplayer = (jsonData, inputField) => {
    let errorList = [];
    Object.entries(jsonData.field_error || {}).map(([field, messageArray]) => {
        errorList.push(field);
    })
    inputField.setErrorList(errorList);
    if (jsonData.message) {
        alertifyToast(jsonData.message, false)
    }
}

const isString = (data) => {
    if (typeof data === "string") {
        return data.trim();
    } else {
        return JSON.stringify(data);
    }
}

export const isValidJSON = (jsonString) => {
    try {
        JSON.parse(isString(jsonString));
        return true;
    } catch (error) {
        return false;
    }
}

export const getJSONData = (json, start = "{", end = "}") => {
    if (isValidJSON(json)) {
        return JSON.parse(isString(json));
    } else {
        let startIndex = json.indexOf(start);
        let endIndex = json.lastIndexOf(end) + end.length;
        let resSubString = json.substring(startIndex, endIndex);
        if (isValidJSON(resSubString)) {
            return JSON.parse(isString(resSubString));
        }
        return {};
    }
}

export const getChosenLabel = (options, value) => {

    let label;
    options.filter((option) => {
        if (option.value === value) label = option.label;
    })
    return label;

}