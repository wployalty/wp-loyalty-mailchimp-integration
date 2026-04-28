import React from 'react';

const Input = ({
                   placeHolder = null,
                   type = "text",
                   value,
                   required,
                   onChange,
                   textColor = "wlmi-text-dark",
                   border,
                   others = "",
                   onfocus = null,
                   onblur = null,
                   min = null,
                   max = null,
                   error = false,
                   id,
                   padding = "2xl:wlmi-p-2.5 wlmi-p-1.5",
                   onKeyDown,
                   height = `${["text", "number"].includes(type) ? "wlmi-h-11" : "wlmi-h-20"}`
               }) => {
    return type === "textarea" ? (
            <textarea
                id={id}
                value={value}
                required={required}
                placeholder={placeHolder}
                className={`${padding}  wlmi-transition wlmi-duration-200 wlmi-ease-in focus:wlmi-outline-none wlmi-rounded focus:wlmi-shadow-none wlmi-antialiased wlmi-bg-white ${border} 2xl:focus:wlmi-border-2  wlmi-w-full  ${textColor} ${others} ${error && "wlmi_input-error"}`}
                onChange={onChange}
                onFocus={onfocus}
            />
        ) :
        (
            <input
                id={id}
                type={type}
                value={value}
                required={required}
                min={type == "number" ? 0 && !min : min}
                max={max}
                placeholder={placeHolder}
                className={`${padding} ${height} wlmi-transition wlmi-duration-200 wlmi-ease-in focus:wlmi-outline-none wlmi-rounded focus:wlmi-shadow-none wlmi-antialiased wlmi-bg-white ${border} 
      2xl:focus:wlmi-border-2 wlmi-w-full ${textColor}  wlmi-tracking-wider ${others} ${error && "wlmi_input-error"} `}
                onChange={onChange}
                onFocus={onfocus}
                onBlur={onblur}
                onKeyDown={onKeyDown}
            />
        );
};

export default Input;