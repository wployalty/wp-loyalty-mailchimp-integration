import React from "react";
import {CommonContext} from "../Context";

export const responsive = {
    text: {
        md: "text-sm 2xl:text-md",
        sm: "text-xs 2xl:text-sm",
    }
}

export const split_content = "w-full flex items-center gap-x-4";

export const getReplacedString = (str) => {
    const {appState} = React.useContext(CommonContext);
    appState.short_code_lists.map((shortcode) => {
        str = str.replaceAll(shortcode.label, shortcode.value);
    });
    return str;
}
export const getHexColor = (color) => {
    return color === "white" ? "#ffffff" : "#000000";
}
export const getTextColor = (color) => {
    return color === "white" ? "text-white" : "text-black";
}
export const getBackgroundColor = (color) => {
    const {commonState} = React.useContext(CommonContext);
    return color === "primary" ? commonState.design.colors.theme.primary : commonState.design.colors.theme.secondary
}
export const getInvertColor = (color) => {
    const {commonState} = React.useContext(CommonContext);
    return color === "primary" ? commonState.design.colors.theme.secondary : commonState.design.colors.theme.primary
}

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

export const getErrorMessage = (errors, filedName) => {
    let error_message;
    if (!errors || !errors.field_error) return "";
    Object.entries(errors.field_error).map(([field, messageArr]) => {
        if (field === filedName) {
            error_message = messageArr[0]
        }
    })
    return error_message;
};

export const confirmAlert = (action, message, ok, cancel, title) => {
    alertify.confirm(title, message, () => {
            action(); // ok action
        },
        () => {
            return true; // cancel action
        }).set('labels', {ok, cancel})
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