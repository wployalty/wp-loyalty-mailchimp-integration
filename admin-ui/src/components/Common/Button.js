import React from 'react';

const Button = ({
                    bgColor = "wlmi-bg-blue_primary",
                    textStyle = "wlmi-text-white wlmi-text-xs 2xl:wlmi-text-sm_14_l_20 ",
                    padding = "wlmi-p-3.5",
                    minwidth = "80px",
                    children,
                    icon = null,
                    others = "",
                    click = null,
                    outline = false,
                    outlineStyle = "wlmi-border-primary",
                    disabled = false,
                    title = "",
                    shadow = false,
                    id = id,
                    extraStyle,
                }) => {
    const outlineStyles = outline ? `wlmi-border ${outlineStyle}` : "";
    return (
        <button
            id={id}
            type="button"
            className={`wlmi-antialiased wlmi-font-medium wlmi-no-underline  wlmi-flex wlmi-items-center wlmi-justify-center wlmi-space-x-2 wlmi-outline-none wlmi-tracking-normal wlmi-whitespace-nowrap wlmi-button ${shadow && "hover:wlmi-shadow-lg "}
             ${bgColor} ${textStyle} ${padding} ${others}
              ${outlineStyles} wlmi-min-w-max wlmi-rounded-md ${disabled ? "wlmi-opacity-50 wlmi-cursor-not-allowed" : "wlmi-cursor-pointer"}`}
            onClick={disabled ? () => {} : click}
            title={title}
            style={{background: `${extraStyle} `}}
        >
            {icon}
            <span style={{
                marginTop: "2px"
            }} className="wlmi-text-xs 2xl:wlmi-text-sm wlmi-font-semibold  ">{children}</span>
        </button>
    );
};

export default Button;

